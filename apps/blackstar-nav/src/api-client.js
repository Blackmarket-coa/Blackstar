const { getConfig } = require('./config');
const { getToken, saveToken } = require('./auth-store');

async function request(path, options = {}) {
  const cfg = getConfig();
  const token = getToken();
  const headers = {
    'Content-Type': 'application/json',
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
    throw new Error(`Request failed ${response.status}: ${JSON.stringify(payload)}`);
  }

  return payload;
}

async function login(email, password) {
  const payload = await request('/int/v1/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  });

  if (payload?.token) {
    saveToken(payload.token);
  }

  return payload;
}

async function listDeliveryRequests() {
  return request('/int/v1/dispatch/requests');
}

async function submitBid({ requestId, price, etaMinutes }) {
  return request(`/int/v1/dispatch/requests/${requestId}/bid`, {
    method: 'POST',
    body: JSON.stringify({ price, eta_minutes: etaMinutes }),
  });
}

module.exports = { request, login, listDeliveryRequests, submitBid };
