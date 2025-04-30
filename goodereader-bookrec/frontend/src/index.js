import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './index.css';

/**
 * Check for dark mode preference and set theme
 */
const setupDarkMode = () => {
  // Check if OS prefers dark mode
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  
  // Find all chatbot containers
  const containers = document.querySelectorAll('.bookrec-chatbot-container');
  
  // Set dark class based on OS preference
  containers.forEach(container => {
    if (prefersDark) {
      container.classList.add('dark');
    } else {
      container.classList.remove('dark');
    }
  });
  
  // Listen for changes in color scheme preference
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {
    containers.forEach(container => {
      if (event.matches) {
        container.classList.add('dark');
      } else {
        container.classList.remove('dark');
      }
    });
  });
};

/**
 * Initialize the Book Recommender React app in each container
 */
document.addEventListener('DOMContentLoaded', () => {
  // Setup dark mode
  setupDarkMode();
  
  // Find all chatbot containers on the page
  const containers = document.querySelectorAll('.bookrec-chatbot-container');
  
  if (containers.length === 0) {
    console.log('No Book Recommender containers found on page');
    return;
  }
  
  // Get global AJAX data
  const ajaxData = window.bookrec_ajax_data || {
    ajax_url: '',
    nonce: '',
    settings: {},
    containers: {}
  };
  
  // Initialize each container
  containers.forEach(container => {
    const containerId = container.id;
    
    // Get container-specific settings
    const containerData = ajaxData.containers[containerId] || {};
    
    // Get genre from data attribute or container data
    const genre = container.dataset.genre || 
                 (containerData.attributes && containerData.attributes.genre) || 
                 '';
    
    // Create settings object for this container
    const settings = {
      ...ajaxData.settings,
      genre,
      ajax_url: ajaxData.ajax_url,
      nonce: ajaxData.nonce,
      default_recommendations: ajaxData.default_recommendations || 3,
      containerId
    };
    
    // Create React root and render App component
    const root = createRoot(container);
    root.render(
      <React.StrictMode>
        <App containerId={containerId} settings={settings} />
      </React.StrictMode>
    );
    
    console.log(`BookRec mounted in container: ${containerId}`);
  });
});