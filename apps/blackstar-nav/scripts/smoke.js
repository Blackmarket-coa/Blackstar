const { getConfig } = require('../src/config');
const { request } = require('../src/api-client');
const { runBidSubmission } = require('../src/bid-flow');

async function main() {
  const cfg = getConfig();
  console.log('BLACKSTAR_API_URL=' + cfg.apiUrl);
  console.log('BLACKSTAR_AUTH_TOKEN_STORAGE_KEY=' + cfg.tokenStorageKey);

  process.env.BLACKSTAR_DRY_RUN = process.env.BLACKSTAR_DRY_RUN || '1';

  const ping = await request('/int/v1/dispatch/requests');
  console.log('api connection check:', JSON.stringify(ping));

  const bid = await runBidSubmission({
    requestId: process.env.BLACKSTAR_SAMPLE_REQUEST_ID || 'req_demo_1001',
    price: Number(process.env.BLACKSTAR_SAMPLE_BID_PRICE || 12.5),
    etaMinutes: Number(process.env.BLACKSTAR_SAMPLE_BID_ETA || 25),
  });

  console.log('bid flow check:', JSON.stringify(bid));
  console.log('nav baseline smoke check passed');
}

main().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
