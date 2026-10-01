import { Component } from '@/types/application';

const componentHelper = {
  getStoredComponent() {
    const stored = localStorage.getItem('component');
    if (stored) {
      return JSON.parse(stored) as Component;
    }
    return null;
  },
  storeComponent(component?: Component) {
    if (!component) {
      localStorage.removeItem('component');
      return;
    }
    localStorage.setItem('component', JSON.stringify(component));
  },
};

export default componentHelper;
