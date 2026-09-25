import Controller from '@ember/controller';
import { action } from '@ember/object';
import { tracked } from '@glimmer/tracking';
import config from '@fleetbase/console/config/environment';

// GET /api/shipment-board-listings/eligible returns only `open` listings, so the
// useful filter is how a listing is taken: claimed directly or awarded from bids.
const CLAIM_POLICIES = ['all', 'first_claim', 'bid'];

// Bearer token from POST /api/auth/token. The console's own session token
// belongs to a Fleetbase user and is rejected by the Blackstar API's
// `operator` guard (it only accepts tokens owned by App\Models\User), so this
// page signs in to the Blackstar API separately. Kept in sessionStorage under
// the same key as the node-registration page, so one sign-in serves both.
const OPERATOR_TOKEN_KEY = 'blackstar.operator.token';

function readOperatorToken() {
    try {
        return window.sessionStorage.getItem(OPERATOR_TOKEN_KEY);
    } catch (_error) {
        return null;
    }
}

function writeOperatorToken(token) {
    try {
        if (token) {
            window.sessionStorage.setItem(OPERATOR_TOKEN_KEY, token);
        } else {
            window.sessionStorage.removeItem(OPERATOR_TOKEN_KEY);
        }
    } catch (_error) {
        // Storage unavailable: the token lives only in this controller.
    }
}

function blackstarApiUrl(path) {
    // API.host is derived from BLACKSTAR_API_URL in config/environment.js (and
    // may be overridden by API_HOST or runtime config); read it at call time
    // so runtime overrides apply.
    return `${String(config.API.host || '').replace(/\/$/, '')}${path}`;
}

async function blackstarApiRequest(path, { method = 'GET', token = null, body = undefined } = {}) {
    const headers = { Accept: 'application/json' };
    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    const response = await fetch(blackstarApiUrl(path), { method, headers, body: body === undefined ? undefined : JSON.stringify(body) });
    const text = await response.text();
    let payload = null;
    try {
        payload = text ? JSON.parse(text) : null;
    } catch (_error) {
        payload = { message: text };
    }

    return { ok: response.ok, status: response.status, statusText: response.statusText, payload };
}

function failureMessage(result, fallback) {
    return `${result.status}: ${result.payload?.message || result.statusText || fallback}`;
}

/**
 * Shipment-board listings the signed-in operator's node is eligible for, read
 * from the Blackstar API. The API returns [] for a user not assigned to a node.
 */
export default class ConsoleDispatchController extends Controller {
    claimPolicies = CLAIM_POLICIES;

    @tracked operatorToken = readOperatorToken();
    @tracked signInEmail = '';
    @tracked signInPassword = '';
    @tracked signInError = null;
    @tracked isSigningIn = false;

    @tracked claimPolicy = 'all';
    @tracked listings = [];
    @tracked isLoading = false;
    @tracked hasLoaded = false;
    @tracked errorMessage = null;

    get endpoint() {
        return blackstarApiUrl('/api/shipment-board-listings/eligible');
    }

    get filteredListings() {
        if (this.claimPolicy === 'all') {
            return this.listings;
        }

        // claim_policy defaults to first_claim but is nullable on create; the
        // API treats anything other than 'bid' as directly claimable.
        return this.listings.filter((listing) => (this.claimPolicy === 'bid' ? listing.claim_policy === 'bid' : listing.claim_policy !== 'bid'));
    }

    @action setClaimPolicyFilter(event) {
        this.claimPolicy = event.target.value;
    }

    @action async signIn(event) {
        event.preventDefault();

        if (this.isSigningIn) {
            return;
        }

        this.isSigningIn = true;
        this.signInError = null;

        try {
            const result = await blackstarApiRequest('/api/auth/token', {
                method: 'POST',
                body: { email: this.signInEmail, password: this.signInPassword, device_name: 'blackstar-console' },
            });

            if (result.ok && result.payload?.token) {
                this.operatorToken = result.payload.token;
                writeOperatorToken(this.operatorToken);
                this.signInPassword = '';
                this.loadListings();
                return;
            }

            const fieldMessage = result.payload?.errors ? Object.values(result.payload.errors).flat()[0] : null;
            this.signInError = fieldMessage ? `${result.status}: ${fieldMessage}` : failureMessage(result, 'Sign-in failed');
        } catch (error) {
            this.signInError = `Could not reach ${blackstarApiUrl('/api/auth/token')}: ${error.message}`;
        } finally {
            this.isSigningIn = false;
        }
    }

    @action async signOut() {
        const token = this.operatorToken;
        this.operatorToken = null;
        writeOperatorToken(null);
        this.listings = [];
        this.hasLoaded = false;
        this.errorMessage = null;

        if (token) {
            try {
                await blackstarApiRequest('/api/auth/token/revoke', { method: 'POST', token });
            } catch (_error) {
                // The local copy is already discarded; a failed revoke leaves
                // only a server-side token nobody holds.
            }
        }
    }

    @action async loadListings() {
        // Re-read storage: the node-registration page may have signed in or out.
        this.operatorToken = readOperatorToken() || this.operatorToken;

        if (this.isLoading || !this.operatorToken) {
            return;
        }

        this.isLoading = true;
        this.errorMessage = null;

        try {
            const result = await blackstarApiRequest('/api/shipment-board-listings/eligible', { token: this.operatorToken });

            if (!result.ok) {
                this.listings = [];
                if (result.status === 401) {
                    // Expired or revoked token: drop it so the sign-in form returns.
                    this.operatorToken = null;
                    writeOperatorToken(null);
                }
                this.errorMessage = failureMessage(result, 'Request failed');
                return;
            }

            this.listings = Array.isArray(result.payload) ? result.payload : [];
        } catch (error) {
            this.listings = [];
            this.errorMessage = `Could not reach ${this.endpoint}: ${error.message}`;
        } finally {
            this.isLoading = false;
            this.hasLoaded = true;
        }
    }
}
