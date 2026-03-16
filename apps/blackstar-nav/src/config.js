function getRequiredEnv(name) {
  const value = process.env[name];
  if (!value) {
    throw new Error(`${name} is required`);
  }
  return value;
}

function getConfig() {
  return {
    apiUrl: getRequiredEnv('BLACKSTAR_API_URL'),
    tokenStorageKey: process.env.BLACKSTAR_AUTH_TOKEN_STORAGE_KEY || 'blackstar.auth.token',
    requestTimeoutMs: Number(process.env.BLACKSTAR_REQUEST_TIMEOUT_MS || 15000),
  };
}

module.exports = { getConfig };
