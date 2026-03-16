const { listDeliveryRequests, submitBid } = require('./api-client');

async function runBidSubmission({ requestId, price, etaMinutes }) {
  const requests = await listDeliveryRequests();
  const bidResponse = await submitBid({ requestId, price, etaMinutes });

  return { requests, bidResponse };
}

module.exports = { runBidSubmission };
