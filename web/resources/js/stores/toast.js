// Jedan toast u cijeloj aplikaciji. fixed bottom centar, auto 6s, role=status.
import { defineStore } from 'pinia';

let timer = null;

export const useToastStore = defineStore('toast', {
  state: () => ({
    message: '',
  }),
  actions: {
    show(message) {
      this.message = message;
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(() => this.hide(), 6000);
    },
    hide() {
      this.message = '';
      if (timer) {
        window.clearTimeout(timer);
        timer = null;
      }
    },
  },
});
