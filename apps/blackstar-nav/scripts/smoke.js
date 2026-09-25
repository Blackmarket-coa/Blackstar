const { getConfig } = require('../src/config');
const { request } = require('../src/api-client');
const { runBidSubmission } = require('../src/bid-flow');

async function main() {
  const cfg = getConfig();
  console.log('BLACKSTAR_API_URL=' + cfg.apiUrl);
  console.log('BLACKSTAR_AUTH_TOKEN_STORAGE_KEY=' + cfg.tokenStorageKey);

  process.env.BLACKSTAR_DRY_RUN = process.env.BLACKSTAR_DRY_RUN || '1';

  const ping = await request('/api/shipment-board-listings/eligible');
  console.log('api connection check:', JSON.stringify(ping));

  const bid = await runBidSubmission({
    listingId: process.env.BLACKSTAR_SAMPLE_LISTING_ID || 'listing_demo_1001',
    amount: Number(process.env.BLACKSTAR_SAMPLE_BID_AMOUNT || 12.5),
    currency: process.env.BLACKSTAR_SAMPLE_BID_CURRENCY || 'USD',
  });

  console.log('bid flow check:', JSON.stringify(bid));
  console.log('nav baseline smoke check passed');
}

main().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
