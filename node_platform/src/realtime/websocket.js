export function registerWebSocket(app) {
  // WebSocket upgrade endpoint for future realtime features
  // Requires @fastify/websocket plugin
  // 
  // Usage (when needed):
  // await app.register(require('@fastify/websocket'));
  // app.get('/ws', { websocket: true }, (connection, req) => {
  //   connection.socket.on('message', (msg) => { ... });
  // });

  app.get('/ws', async (req, reply) => {
    return reply.code(501).send({ error: 'WebSocket not implemented yet' });
  });
}