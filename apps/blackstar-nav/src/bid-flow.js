const { listEligibleListings, submitBid } = require('./api-client');

async function runBidSubmission({ listingId, amount, currency, note }) {
  const listings = await listEligibleListings();

  // Dry runs return a request description rather than a list, so the checks
  // below only apply to a real response. They mirror the API's own refusals so
  // a driver gets a clear reason before the round trip.
  if (Array.isArray(listings)) {
    const listing = listings.find((candidate) => String(candidate.id) === String(listingId));
    if (!listing) {
      throw new Error(`Listing ${listingId} is not among this node's eligible listings`);
    }
    if (listing.claim_policy !== 'bid') {
      throw new Error(`Listing ${listingId} is claimed directly, not awarded from bids`);
    }
  }

  const bidResponse = await submitBid({ listingId, amount, currency, note });

  return { listings, bidResponse };
}

module.exports = { runBidSubmission };
