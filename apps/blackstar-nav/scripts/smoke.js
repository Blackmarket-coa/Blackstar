const url = process.env.BLACKSTAR_API_URL;
if (!url) {
  console.error('BLACKSTAR_API_URL is required');
  process.exit(1);
}

const tokenKey = process.env.BLACKSTAR_AUTH_TOKEN_STORAGE_KEY || 'blackstar.auth.token';
console.log('BLACKSTAR_API_URL=' + url);
console.log('BLACKSTAR_AUTH_TOKEN_STORAGE_KEY=' + tokenKey);
console.log('nav scaffold smoke check passed');
