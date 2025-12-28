// Helper to call assistant endpoints
async function postJSON(url, data) {
  const headers = { 'Accept': 'application/json' };
  const metaCsrf = document.querySelector('meta[name="csrf-token"]')?.content;
  const token = metaCsrf || (typeof window.CSRF_TOKEN !== 'undefined' ? window.CSRF_TOKEN : '');
  if (token) headers['X-CSRF-Token'] = token;
  const res = await fetch(url, {
    method: 'POST',
    headers,
    body: data instanceof FormData ? data : new URLSearchParams(data)
  });
  return res.json();
}

// Translation helper used on report pages
async function translateInline(text, direction) {
  const r = await postJSON('./assistant/translate.php', { text, direction });
  return r.translated || text;
}
