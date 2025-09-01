// Minimal backend HTTP server
import http from 'http'

const port = process.env.PORT || 3001

const handler = (req, res) => {
  if (req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' })
    res.end(JSON.stringify({ status: 'ok', service: 'backend', ts: Date.now() }))
    return
  }
  if (req.url?.startsWith('/api')) {
    res.writeHead(200, { 'Content-Type': 'application/json' })
    res.end(JSON.stringify({ message: 'Engine backend API placeholder' }))
    return
  }
  res.writeHead(404)
  res.end()
}

http.createServer(handler).listen(port, () => {
  console.log(`[backend] listening on ${port}`)
})

