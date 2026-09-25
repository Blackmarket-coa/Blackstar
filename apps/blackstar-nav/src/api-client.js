const { getConfig } = require('./config');
const { getToken, saveToken } = require('./auth-store');

async function request(path, options = {}) {
  const cfg = getConfig();
  const token = getToken();
  const headers = {
    'Content-Type': 'application/json',
    // Without this Laravel answers an auth or validation failure with a
    // redirect to a login page instead of a JSON error body.
    Accept: 'application/json',
    ...(options.headers || {}),
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const url = `${cfg.apiUrl.replace(/\/$/, '')}${path}`;

  if (process.env.BLACKSTAR_DRY_RUN === '1') {
    return { dryRun: true, url, method: options.method || 'GET', body: options.body || null };
  }

  const response = await fetch(url, {
    ...options,
    headers,
  });

  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};

  if (!response.ok) {
    const error = new Error(`Request failed ${response.status}: ${JSON.stringify(payload)}`);
    error.status = response.status;
    error.payload = payload;
    throw error;
  }

  return payload;
}

// POST /api/auth/token {email, password, device_name?} -> {token, token_type: 'Bearer'}
async function login(email, password, deviceName = 'blackstar-nav') {
  const payload = await request('/api/auth/token', {
    method: 'POST',
    body: JSON.stringify({ email, password, device_name: deviceName }),
  });

  if (payload?.token) {
    saveToken(payload.token);
  }

  return payload;
}

// GET /api/shipment-board-listings/eligible — open listings the caller's node
// is eligible for (empty when the user is not assigned to a node).
async function listEligibleListings() {
  return request('/api/shipment-board-listings/eligible');
}

// POST /api/shipment-board-listings/{listing}/bids {amount, currency?, note?}.
// Only accepted on listings whose claim_policy is 'bid'; a node has one bid per
// listing and re-submitting replaces it.
async function submitBid({ listingId, amount, currency, note }) {
  const body = { amount };
  if (currency) body.currency = currency;
  if (note) body.note = note;

  return request(`/api/shipment-board-listings/${encodeURIComponent(listingId)}/bids`, {
    method: 'POST',
    body: JSON.stringify(body),
  });
}

module.exports = { request, login, listEligibleListings, submitBid };
