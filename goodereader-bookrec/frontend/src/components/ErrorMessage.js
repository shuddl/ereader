import React from 'react';

/**
 * Error message component
 */
const ErrorMessage = ({ message, onDismiss = null }) => {
  return (
    <div className="bookrec-error flex items-center p-3 mb-4 bg-gray-100 dark:bg-gray-800 border-l-4 border-gray-700 dark:border-gray-500 rounded">
      <div className="flex-grow">
        <p className="text-gray-800 dark:text-gray-200">{message}</p>
      </div>
      
      {onDismiss && (
        <button 
          onClick={onDismiss}
          className="ml-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
          aria-label="Dismiss error"
        >
          ✕
        </button>
      )}
    </div>
  );
};

export default ErrorMessage;