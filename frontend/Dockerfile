FROM node:20-alpine
WORKDIR /app

USER node
COPY --chown=node:node server.js ./

ENV PORT=3000
EXPOSE 3000
CMD ["node", "server.js"]
