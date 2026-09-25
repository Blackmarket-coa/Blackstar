const test = require('node:test');
const assert = require('node:assert/strict');

const { login, listEligibleListings, submitBid } = require('../src/api-client');
const { runBidSubmission } = require('../src/bid-flow');
const { getToken, clearToken } = require('../src/auth-store');

// Records every fetch and answers from a queue, so each test asserts the exact
// method, URL, headers and body the client sends to the Blackstar API.
function stubFetch(t, responses) {
  const calls = [];
  const original = global.fetch;
  global.fetch = async (url, options) => {
    calls.push({ url, options });
    const next = responses.shift() || { status: 200, body: {} };
    return {
      ok: next.status >= 200 && next.status < 300,
      status: next.status,
      text: async () => JSON.stringify(next.body),
    };
  };
  t.after(() => {
    global.fetch = original;
  });
  return calls;
}

test.beforeEach(() => {
  process.env.BLACKSTAR_API_URL = 'https://api.blackstar.test/';
  delete process.env.BLACKSTAR_DRY_RUN;
  clearToken();
});

test('login posts to /api/auth/token and stores the bearer token', async (t) => {
  const calls = stubFetch(t, [{ status: 200, body: { token: 'tok_123', token_type: 'Bearer' } }]);

  const payload = await login('driver@example.com', 'secret', 'pixel-7');

  assert.equal(calls[0].url, 'https://api.blackstar.test/api/auth/token');
  assert.equal(calls[0].options.method, 'POST');
  assert.deepEqual(JSON.parse(calls[0].options.body), {
    email: 'driver@example.com',
    password: 'secret',
    device_name: 'pixel-7',
  });
  assert.equal(payload.token_type, 'Bearer');
  assert.equal(getToken(), 'tok_123');
});

test('authenticated requests carry the token and ask for JSON', async (t) => {
  const calls = stubFetch(t, [
    { status: 200, body: { token: 'tok_abc', token_type: 'Bearer' } },
    { status: 200, body: [] },
  ]);

  await login('driver@example.com', 'secret');
  await listEligibleListings();

  assert.equal(calls[1].url, 'https://api.blackstar.test/api/shipment-board-listings/eligible');
  assert.equal(calls[1].options.headers.Authorization, 'Bearer tok_abc');
  assert.equal(calls[1].options.headers.Accept, 'application/json');
});

test('submitBid posts amount/currency/note to the plural /bids route', async (t) => {
  const calls = stubFetch(t, [{ status: 201, body: { id: 'bid_1', amount: '40.00' } }]);

  await submitBid({ listingId: 'lst/1', amount: 40, currency: 'USD', note: 'van' });

  assert.equal(calls[0].url, 'https://api.blackstar.test/api/shipment-board-listings/lst%2F1/bids');
  assert.equal(calls[0].options.method, 'POST');
  assert.deepEqual(JSON.parse(calls[0].options.body), { amount: 40, currency: 'USD', note: 'van' });
});

test('failed requests expose status and payload', async (t) => {
  stubFetch(t, [{ status: 422, body: { message: 'Bidding is not enabled for this listing.' } }]);

  await assert.rejects(submitBid({ listingId: 'lst_1', amount: 5 }), (error) => {
    assert.equal(error.status, 422);
    assert.equal(error.payload.message, 'Bidding is not enabled for this listing.');
    return true;
  });
});

test('runBidSubmission bids on an eligible bid-policy listing', async (t) => {
  const calls = stubFetch(t, [
    { status: 200, body: [{ id: 'lst_1', claim_policy: 'bid' }] },
    { status: 201, body: { id: 'bid_1' } },
  ]);

  const result = await runBidSubmission({ listingId: 'lst_1', amount: 12.5 });

  assert.equal(calls.length, 2);
  assert.equal(result.bidResponse.id, 'bid_1');
});

test('runBidSubmission refuses listings that are not eligible or not bid-policy', async (t) => {
  const calls = stubFetch(t, [
    { status: 200, body: [{ id: 'lst_1', claim_policy: 'first_claim' }] },
    { status: 200, body: [] },
  ]);

  await assert.rejects(runBidSubmission({ listingId: 'lst_1', amount: 1 }), /claimed directly/);
  await assert.rejects(runBidSubmission({ listingId: 'lst_1', amount: 1 }), /not among/);
  assert.equal(calls.length, 2);
});

test('dry run describes the request without calling fetch', async (t) => {
  const calls = stubFetch(t, []);
  process.env.BLACKSTAR_DRY_RUN = '1';

  const result = await runBidSubmission({ listingId: 'lst_1', amount: 3 });

  assert.equal(calls.length, 0);
  assert.equal(result.bidResponse.url, 'https://api.blackstar.test/api/shipment-board-listings/lst_1/bids');
});
