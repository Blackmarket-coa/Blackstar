let inMemoryToken = null;

function saveToken(token) {
  inMemoryToken = token;
}

function getToken() {
  return inMemoryToken;
}

function clearToken() {
  inMemoryToken = null;
}

module.exports = { saveToken, getToken, clearToken };
