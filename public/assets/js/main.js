// Public copy of main.js
async function postJSON(url, data) {
  const headers = { 'Accept': 'application/json' };
  if (typeof window.CSRF_TOKEN !== 'undefined') headers['X-CSRF-Token'] = window.CSRF_TOKEN;
  const res = await fetch(url, {
    method: 'POST',
    headers,
    body: data instanceof FormData ? data : new URLSearchParams(data)
  });
  return res.json();
}

async function translateInline(text, direction) {
  const r = await postJSON('./assistant/translate.php', { text, direction });
  return r.translated || text;
}
