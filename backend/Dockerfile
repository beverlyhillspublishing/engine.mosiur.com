FROM node:20-alpine
WORKDIR /app

# Non-root for security
USER node

# Copy only what we need
COPY --chown=node:node server.js ./

ENV PORT=3001
EXPOSE 3001

CMD ["node", "server.js"]
