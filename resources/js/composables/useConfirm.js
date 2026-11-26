import { reactive } from 'vue'

const state = reactive({
  open: false,
  title: 'Are you sure?',
  message: '',
  confirmText: 'Confirm',
  cancelText: 'Cancel',
  variant: 'primary',
  resolver: null,
})

function confirm(options = {}) {
  return new Promise((resolve) => {
    state.title = options.title ?? 'Are you sure?'
    state.message = options.message ?? ''
    state.confirmText = options.confirmText ?? 'Confirm'
    state.cancelText = options.cancelText ?? 'Cancel'
    state.variant = options.variant ?? 'primary'
    state.resolver = resolve
    state.open = true
  })
}

function settle(value) {
  state.open = false
  const resolver = state.resolver
  state.resolver = null
  if (resolver) {
    resolver(value)
  }
}

export function useConfirm() {
  return {
    state,
    confirm,
    accept: () => settle(true),
    cancel: () => settle(false),
  }
}
