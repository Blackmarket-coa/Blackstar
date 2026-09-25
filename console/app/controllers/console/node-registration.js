import Controller from '@ember/controller';
import { action } from '@ember/object';
import { tracked } from '@glimmer/tracking';
import config from '@fleetbase/console/config/environment';

// Bearer token from POST /api/auth/token. The console's own session token
// belongs to a Fleetbase user and is rejected by the Blackstar API's
// `operator` guard (it only accepts tokens owned by App\Models\User), so this
// page signs in to the Blackstar API separately. Kept in sessionStorage under
// the same key as the dispatch page, so one sign-in serves both.
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
 * Registers a node through the Blackstar API: POST /api/nodes.
 *
 * Fields mirror NodeController::store validation. Attestation hashes are not
 * collected here — they are set by POST /api/nodes/{node}/attest, which also
 * activates the node. The API authorizes creation only for a user already
 * assigned to a node (NodePolicy::create), so other accounts get a 403.
 */
export default class ConsoleNodeRegistrationController extends Controller {
    @tracked operatorToken = readOperatorToken();
    @tracked signInEmail = '';
    @tracked signInPassword = '';
    @tracked signInError = null;
    @tracked isSigningIn = false;

    @tracked nodeId = '';
    @tracked legalEntityName = '';
    @tracked jurisdiction = '';
    @tracked serviceRadius = '';
    @tracked latitude = '';
    @tracked longitude = '';
    @tracked contactEmail = '';
    @tracked contactPhone = '';
    @tracked transportCapabilities = '';

    @tracked isSubmitting = false;
    @tracked createdNode = null;
    @tracked errorMessage = null;
    @tracked fieldErrors = null;

    get endpoint() {
        return blackstarApiUrl('/api/nodes');
    }

    // Called on each visit: the dispatch page may have signed in or out.
    refreshOperatorToken() {
        this.operatorToken = readOperatorToken() || this.operatorToken;
    }

    buildPayload() {
        const trimmed = (value) => String(value ?? '').trim();
        const payload = {
            node_id: trimmed(this.nodeId),
            legal_entity_name: trimmed(this.legalEntityName),
            jurisdiction: trimmed(this.jurisdiction),
            service_radius: trimmed(this.serviceRadius),
        };

        if (trimmed(this.latitude) !== '') {
            payload.latitude = trimmed(this.latitude);
        }
        if (trimmed(this.longitude) !== '') {
            payload.longitude = trimmed(this.longitude);
        }

        const contact = {};
        if (trimmed(this.contactEmail) !== '') {
            contact.email = trimmed(this.contactEmail);
        }
        if (trimmed(this.contactPhone) !== '') {
            contact.phone = trimmed(this.contactPhone);
        }
        if (Object.keys(contact).length > 0) {
            payload.contact = contact;
        }

        const capabilities = trimmed(this.transportCapabilities)
            .split(',')
            .map((value) => value.trim())
            .filter(Boolean);
        if (capabilities.length > 0) {
            payload.transport_capabilities = capabilities;
        }

        return payload;
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

        if (token) {
            try {
                await blackstarApiRequest('/api/auth/token/revoke', { method: 'POST', token });
            } catch (_error) {
                // The local copy is already discarded; a failed revoke leaves
                // only a server-side token nobody holds.
            }
        }
    }

    @action async submitNodeRegistration(event) {
        event.preventDefault();

        if (this.isSubmitting) {
            return;
        }

        this.isSubmitting = true;
        this.createdNode = null;
        this.errorMessage = null;
        this.fieldErrors = null;

        try {
            const result = await blackstarApiRequest('/api/nodes', { method: 'POST', token: this.operatorToken, body: this.buildPayload() });

            if (result.ok) {
                this.createdNode = result.payload;
                return;
            }

            if (result.status === 401) {
                // Expired or revoked token: drop it so the sign-in form returns.
                this.operatorToken = null;
                writeOperatorToken(null);
            }
            if (result.status === 422 && result.payload?.errors) {
                this.fieldErrors = result.payload.errors;
            }
            this.errorMessage = failureMessage(result, 'Request failed');
        } catch (error) {
            this.errorMessage = `Could not reach ${this.endpoint}: ${error.message}`;
        } finally {
            this.isSubmitting = false;
        }
    }
}
