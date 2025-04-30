import React, { useCallback, memo } from 'react';

/**
 * Chat input component with send button
 * Memoized to prevent unnecessary re-renders
 */
const ChatInput = memo(({ 
  value, 
  onChange, 
  onSubmit, 
  isLoading, 
  placeholder = 'Type your message...' 
}) => {
  // Handle form submission - memoized with useCallback
  const handleSubmit = useCallback((e) => {
    e.preventDefault();
    if (value.trim() && !isLoading) {
      onSubmit();
    }
  }, [value, isLoading, onSubmit]);

  // Handle Enter key press - memoized with useCallback
  const handleKeyDown = useCallback((e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSubmit(e);
    }
  }, [handleSubmit]);

  return (
    <form onSubmit={handleSubmit} className="bookrec-input-form flex">
      <input
        type="text"
        value={value}
        onChange={onChange}
        onKeyDown={handleKeyDown}
        disabled={isLoading}
        placeholder={placeholder}
        className="bookrec-input w-full p-3 border border-gray-300 dark:border-gray-600 rounded-l-lg focus:outline-none focus:ring-1 focus:ring-black dark:focus:ring-white bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 dark:placeholder-gray-400"
        aria-label="Message input"
      />
      <button
        type="submit"
        disabled={isLoading || !value.trim()}
        className={`
          bookrec-send-button py-3 px-4 rounded-r-lg transition-colors duration-200
          ${isLoading || !value.trim() 
            ? 'bg-gray-300 dark:bg-gray-700 text-gray-500 dark:text-gray-400 cursor-not-allowed' 
            : 'bg-black dark:bg-gray-800 text-white hover:bg-gray-800 dark:hover:bg-gray-700'
          }
        `}
        aria-label="Send message"
      >
        {isLoading ? 'Sending...' : 'Send'}
      </button>
    </form>
  );
}, (prevProps, nextProps) => {
  // Custom comparison function for memo
  // Only re-render if these props change
  return (
    prevProps.value === nextProps.value &&
    prevProps.isLoading === nextProps.isLoading &&
    prevProps.placeholder === nextProps.placeholder
  );
});

export default ChatInput;