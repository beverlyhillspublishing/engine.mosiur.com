# Minimal runtime to satisfy DigitalOcean App Platform build/run
# Temporary health server until backend/frontend Dockerfiles are implemented

FROM node:20-alpine AS runner
WORKDIR /app

# Write a tiny HTTP server that answers on $PORT (defaults 8080)
RUN printf "const http=require('http');\nconst port=process.env.PORT||8080;\nconst msg=JSON.stringify({status:'ok', service:'engine.mosiur.com', ts:Date.now()});\nhttp.createServer((req,res)=>{\n  if(req.url==='/health' || req.url==='/') {\n    res.writeHead(200,{ 'Content-Type':'application/json'});\n    res.end(msg);\n  } else {\n    res.writeHead(404);\n    res.end();\n  }\n}).listen(port,()=>console.log('listening on',port));\n" > server.js

ENV PORT=8080
EXPOSE 8080
CMD ["node", "server.js"]
