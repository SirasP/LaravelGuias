const http = require('http');
const WebSocket = require('ws');

const wsPort = Number(process.env.WEBSOCKET_PORT || 3000);
const notifyPort = Number(process.env.WEBSOCKET_NOTIFY_PORT || 3001);
const adminId = Number(process.env.WEBSOCKET_ADMIN_ID || 1);
const users = new Map();

const websocketServer = new WebSocket.Server({ port: wsPort });

websocketServer.on('connection', (socket) => {
    socket.on('message', (message) => {
        let payload;

        try {
            payload = JSON.parse(message.toString());
        } catch {
            return;
        }

        if (payload.type !== 'register' || !payload.userId) {
            return;
        }

        socket.userId = Number(payload.userId);
        socket.userName = payload.name || 'Usuario';
        users.set(socket.userId, socket);

        const admin = users.get(adminId);
        if (admin && admin.readyState === WebSocket.OPEN && socket.userId !== adminId) {
            admin.send(JSON.stringify({
                type: 'user_connected',
                userId: socket.userId,
                name: socket.userName,
            }));
        }
    });

    socket.on('close', () => {
        if (socket.userId && users.get(socket.userId) === socket) {
            users.delete(socket.userId);
        }
    });
});

http.createServer((request, response) => {
    if (request.method === 'GET' && request.url === '/online') {
        response.writeHead(200, { 'Content-Type': 'application/json' });
        response.end(JSON.stringify([...users.entries()].map(([id, socket]) => ({
            id,
            name: socket.userName,
        }))));
        return;
    }

    if (request.method !== 'POST' || request.url !== '/notify') {
        response.writeHead(404);
        response.end();
        return;
    }

    let body = '';
    request.on('data', (chunk) => { body += chunk.toString(); });
    request.on('end', () => {
        try {
            const payload = JSON.parse(body);
            const admin = users.get(adminId);

            if (admin && admin.readyState === WebSocket.OPEN) {
                admin.send(JSON.stringify({
                    type: 'xml_entrada',
                    titulo: payload.titulo,
                    mensaje: payload.mensaje,
                }));
            }

            response.writeHead(200);
            response.end('OK');
        } catch {
            response.writeHead(400);
            response.end('Invalid JSON');
        }
    });
}).listen(notifyPort);

console.log(`WebSocket listening on ${wsPort}; notifications on ${notifyPort}`);
