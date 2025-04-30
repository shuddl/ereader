import React, { useState } from 'react';

/**
 * Author search component with suggestion functionality
 */
const AuthorSearchBar = ({ onSearch, isActive }) => {
  const [authorName, setAuthorName] = useState('');
  const [isExpanded, setIsExpanded] = useState(false);
  
  // Handle search submission
  const handleSubmit = (e) => {
    e.preventDefault();
    if (authorName.trim().length > 0) {
      onSearch(authorName.trim());
      setAuthorName('');
      setIsExpanded(false);
    }
  };
  
  // Only render if author search is active
  if (!isActive) return null;
  
  return (
    <div className="bookrec-author-search mt-2 mb-4">
      <button 
        onClick={() => setIsExpanded(!isExpanded)}
        className="flex items-center text-sm px-3 py-1.5 bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors"
      >
        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
          <path fillRule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clipRule="evenodd" />
        </svg>
        {isExpanded ? 'Close' : 'Search by Author'}
      </button>
      
      {isExpanded && (
        <div className="mt-2 p-3 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm animate-fade-in">
          <form onSubmit={handleSubmit} className="flex">
            <input
              type="text"
              value={authorName}
              onChange={(e) => setAuthorName(e.target.value)}
              placeholder="Enter author name..."
              className="flex-grow border border-gray-300 dark:border-gray-600 rounded-l-lg px-3 py-2 focus:border-black dark:focus:border-gray-400 focus:ring-1 focus:ring-black dark:focus:ring-gray-400 focus:outline-none bg-white dark:bg-gray-700 text-gray-900 dark:text-white dark:placeholder-gray-400"
              aria-label="Author name"
            />
            <button 
              type="submit"
              className="px-4 py-2 bg-black dark:bg-gray-600 text-white rounded-r-lg hover:bg-gray-800 dark:hover:bg-gray-700 transition-colors"
              disabled={authorName.trim().length === 0}
            >
              Search
            </button>
          </form>
          <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
            Enter an author's name to get book recommendations by them.
          </p>
        </div>
      )}
    </div>
  );
};

export default AuthorSearchBar;