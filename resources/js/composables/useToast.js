import { reactive } from 'vue'

let nextId = 1
const state = reactive({ items: [] })

function dismiss(id) {
  const index = state.items.findIndex((toast) => toast.id === id)
  if (index !== -1) {
    state.items.splice(index, 1)
  }
}

function push(type, title, message = '', timeout = 5000) {
  const id = nextId++
  state.items.push({ id, type, title, message })
  if (timeout > 0) {
    window.setTimeout(() => dismiss(id), timeout)
  }
  return id
}

export function useToast() {
  return {
    toasts: state.items,
    success: (title, message = '') => push('success', title, message),
    error: (title, message = '') => push('error', title, message, 6500),
    info: (title, message = '') => push('info', title, message),
    warning: (title, message = '') => push('warning', title, message, 6000),
    dismiss,
    clear: () => state.items.splice(0),
  }
}
