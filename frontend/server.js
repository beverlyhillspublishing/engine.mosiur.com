// Minimal frontend HTTP server (placeholder UI)
import http from 'http'

const port = process.env.PORT || 3000

const html = `<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Engine OS Frontend</title>
    <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu;max-width:720px;margin:48px auto;padding:0 16px} code{background:#f4f4f7;padding:2px 6px;border-radius:4px}</style>
  </head>
  <body>
    <h1>Engine OS — Frontend</h1>
    <p>This is a placeholder frontend. Replace with your Next.js app.</p>
    <p>Health check: <a href="/health">/health</a></p>
  </body>
</html>`

const handler = (req, res) => {
  if (req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' })
    res.end(JSON.stringify({ status: 'ok', service: 'frontend', ts: Date.now() }))
    return
  }
  if (req.url === '/' || req.url === '/index.html') {
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' })
    res.end(html)
    return
  }
  res.writeHead(404)
  res.end()
}

http.createServer(handler).listen(port, () => {
  console.log(`[frontend] listening on ${port}`)
})

