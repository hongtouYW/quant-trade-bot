import { useEffect, useRef, useCallback } from 'react';
import { io } from 'socket.io-client';

let _socket = null;

function getSocket() {
  if (_socket) return _socket;

  const role = window.location.pathname.startsWith('/admin') ? 'admin' : 'agent';
  const token = localStorage.getItem(`${role}_access_token`);
  if (!token) return null;

  _socket = io({
    path: '/socket.io/',
    auth: { token },
    transports: ['websocket', 'polling'],
    reconnection: true,
    reconnectionDelay: 3000,
    reconnectionAttempts: 10,
  });

  _socket.on('disconnect', () => {
    _socket = null;
  });

  return _socket;
}

/**
 * Subscribe to a socket.io event. Automatically connects and cleans up.
 * Falls back gracefully if WebSocket is unavailable.
 */
export function useSocket(event, callback) {
  const cbRef = useRef(callback);
  cbRef.current = callback;

  useEffect(() => {
    const socket = getSocket();
    if (!socket) return;

    const handler = (data) => cbRef.current(data);
    socket.on(event, handler);

    return () => {
      socket.off(event, handler);
    };
  }, [event]);
}

/**
 * Disconnect the global socket (call on logout).
 */
export function disconnectSocket() {
  if (_socket) {
    _socket.disconnect();
    _socket = null;
  }
}
