// Global event system for triggering modals from outside React components
type Listener<T = any> = (data: T) => void;

class ModalEventEmitter {
  private listeners: { [key: string]: Listener[] } = {};

  on(event: string, callback: Listener) {
    if (!this.listeners[event]) {
      this.listeners[event] = [];
    }
    this.listeners[event].push(callback);
  }

  off(event: string, callback: Listener) {
    if (!this.listeners[event]) return;
    this.listeners[event] = this.listeners[event].filter(cb => cb !== callback);
  }

  emit(event: string, data?: any) {
    if (!this.listeners[event]) return;
    this.listeners[event].forEach(callback => callback(data));
  }
}

export const modalEvents = new ModalEventEmitter();

// Event types
export const MODAL_EVENTS = {
  OPEN_LOGIN_MODAL: 'OPEN_LOGIN_MODAL',
  OPEN_REGISTER_MODAL: 'OPEN_REGISTER_MODAL',
  OPEN_LOGOUT_MODAL: 'OPEN_LOGOUT_MODAL',
} as const;
