import React from 'react';

/**
 * Loading indicator component
 * 
 * @param {Object} props
 * @param {string} props.message - The loading message to display
 * @param {string} props.type - The type of loading indicator ('full', 'typing', 'spinner')
 */
const LoadingIndicator = ({ message = 'Loading...', type = 'full' }) => {
  // Different loading indicator styles based on type
  if (type === 'typing') {
    return (
      <div className="bookrec-typing-indicator flex items-center p-2 rounded-lg bg-gray-100 dark:bg-gray-800 inline-block ml-4">
        <span className="text-sm text-gray-600 dark:text-gray-400">{message}</span>
        <span className="typing-animation ml-1">
          <span className="dot bg-gray-500 dark:bg-gray-400"></span>
          <span className="dot bg-gray-500 dark:bg-gray-400"></span>
          <span className="dot bg-gray-500 dark:bg-gray-400"></span>
        </span>
      </div>
    );
  }
  
  if (type === 'spinner') {
    return (
      <div className="bookrec-spinner-container flex justify-center items-center my-2">
        <div className="bookrec-spinner w-5 h-5 border-2 border-gray-300 dark:border-gray-600 border-t-black dark:border-t-white rounded-full animate-spin"></div>
        <span className="ml-2 text-sm text-gray-600 dark:text-gray-400">{message}</span>
      </div>
    );
  }
  
  // Default full loading indicator
  return (
    <div className="bookrec-loading-indicator w-full flex flex-col justify-center items-center p-4">
      <div className="bookrec-spinner w-8 h-8 border-4 border-gray-200 dark:border-gray-700 border-t-black dark:border-t-white rounded-full animate-spin mb-2"></div>
      <p className="text-gray-700 dark:text-gray-300">{message}</p>
    </div>
  );
};

export default LoadingIndicator;