import React, { useState, useEffect, useCallback, memo } from 'react';
import RecommendationCard from './RecommendationCard';

// Email PDF Form component - Memoized
const EmailPdfForm = memo(({ sessionId, onSuccess, onError }) => {
  const [email, setEmail] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [formVisible, setFormVisible] = useState(false);
  const [message, setMessage] = useState({ text: '', type: '' });
  
  const handleSubmit = useCallback(async (e) => {
    e.preventDefault();
    setMessage({ text: '', type: '' });
    setIsSubmitting(true);
    
    try {
      // Get AJAX URL and nonce
      const ajaxUrl = window.bookrec_ajax_data?.ajax_url;
      const emailNonce = window.bookrec_ajax_data?.email_nonce;
      
      // Create form data
      const formData = new FormData();
      formData.append('action', 'bookrec_email_recs');
      formData.append('nonce', emailNonce);
      formData.append('session_id', sessionId);
      formData.append('email', email);
      
      // Send request
      const response = await fetch(ajaxUrl, {
        method: 'POST',
        body: formData,
      });
      
      const data = await response.json();
      
      if (data.success) {
        setMessage({ text: data.data.message, type: 'success' });
        setEmail('');
        if (onSuccess) onSuccess(data);
        
        // Auto-hide form after success
        setTimeout(() => {
          setFormVisible(false);
        }, 3000);
      } else {
        setMessage({ text: data.data.message, type: 'error' });
        if (onError) onError(data);
      }
    } catch (error) {
      console.error('Error sending email:', error);
      setMessage({ text: 'Failed to send email. Please try again.', type: 'error' });
      if (onError) onError(error);
    } finally {
      setIsSubmitting(false);
    }
  }, [email, sessionId, onSuccess, onError]);
  
  const toggleForm = useCallback(() => {
    setFormVisible(!formVisible);
    if (!formVisible) {
      setMessage({ text: '', type: '' });
    }
  }, [formVisible]);
  
  return (
    <div className="bookrec-email-pdf-container mt-4 mb-4">
      <button 
        onClick={toggleForm}
        className="bookrec-toggle-email-form px-4 py-2 text-sm rounded border bg-white dark:bg-gray-800 text-black dark:text-white border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
      >
        {formVisible ? 'Cancel' : 'Email These Recommendations'}
      </button>
      
      {formVisible && (
        <div className="bookrec-email-form mt-3 p-4 border border-gray-200 dark:border-gray-700 rounded bg-gray-50 dark:bg-gray-800">
          <form onSubmit={handleSubmit}>
            <div className="mb-3">
              <label htmlFor="bookrec-email" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Email Address:
              </label>
              <input
                type="email"
                id="bookrec-email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                placeholder="your@email.com"
                className="w-full p-2 border border-gray-300 dark:border-gray-600 rounded text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white dark:placeholder-gray-400"
                disabled={isSubmitting}
              />
            </div>
            <button
              type="submit"
              disabled={isSubmitting}
              className="px-4 py-2 text-sm rounded border bg-black dark:bg-gray-700 text-white hover:bg-gray-800 dark:hover:bg-gray-600 transition-colors disabled:opacity-50"
            >
              {isSubmitting ? 'Sending...' : 'Send PDF'}
            </button>
            
            {message.text && (
              <div className={`mt-3 p-2 rounded text-sm ${
                message.type === 'success' 
                  ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-300' 
                  : 'bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300'
              }`}>
                {message.text}
              </div>
            )}
          </form>
        </div>
      )}
    </div>
  );
}, (prevProps, nextProps) => prevProps.sessionId === nextProps.sessionId);

/**
 * Displays a list of book recommendations with intro text and filtering
 * Memoized to prevent unnecessary re-renders
 */
const RecommendationList = memo(({ recommendations, explanatoryText, concludingQuestion, ctaVariation, sessionId }) => {
  const [visibleBooks, setVisibleBooks] = useState([]);
  const [filteredBooks, setFilteredBooks] = useState([]);
  const [filter, setFilter] = useState('all');
  const [uniqueGenres, setUniqueGenres] = useState([]);
  
  // If no recommendations, don't render anything
  if (!recommendations || recommendations.length === 0) {
    return null;
  }

  // Initialize filtered books when recommendations change
  useEffect(() => {
    setFilteredBooks(recommendations);
    
    // Extract unique genres from all recommendations
    const genres = new Set();
    recommendations.forEach(book => {
      if (book.genre) {
        genres.add(book.genre);
      }
    });
    
    setUniqueGenres(Array.from(genres));
    
    // Staggered animation for cards appearing
    setVisibleBooks([]);
    const animationDelay = 200; // milliseconds between each card appearing
    
    // Show each book with a delay
    recommendations.forEach((book, index) => {
      setTimeout(() => {
        setVisibleBooks(prev => [...prev, index]);
      }, index * animationDelay);
    });
  }, [recommendations]);
  
  // Filter recommendations by genre - memoized with useCallback
  const handleFilterChange = useCallback((genre) => {
    setFilter(genre);
    if (genre === 'all') {
      setFilteredBooks(recommendations);
    } else {
      setFilteredBooks(recommendations.filter(book => book.genre === genre));
    }
    
    // Reset animations for filtered books
    setVisibleBooks([]);
    const animationDelay = 200;
    
    // Get indexes of filtered books
    const filteredIndexes = genre === 'all' 
      ? recommendations.map((_, index) => index)
      : recommendations
          .map((book, index) => book.genre === genre ? index : -1)
          .filter(index => index !== -1);
    
    // Apply staggered animation to filtered books
    filteredIndexes.forEach((bookIndex, animIndex) => {
      setTimeout(() => {
        setVisibleBooks(prev => [...prev, bookIndex]);
      }, animIndex * animationDelay);
    });
  }, [recommendations]);

  return (
    <div className="bookrec-recommendations mt-2 mb-6">
      {explanatoryText && (
        <div className="bookrec-recommendations-intro mb-4">
          <p className="text-gray-800 dark:text-gray-200">{explanatoryText}</p>
        </div>
      )}
      
      {/* Genre filter buttons - only show if we have genres */}
      {uniqueGenres.length > 0 && (
        <div className="bookrec-filter-controls mb-4 flex flex-wrap gap-2">
          <button 
            onClick={() => handleFilterChange('all')}
            className={`px-3 py-1 text-sm rounded border ${
              filter === 'all' 
                ? 'bg-black dark:bg-gray-700 text-white' 
                : 'bg-white dark:bg-gray-800 text-black dark:text-white border-gray-300 dark:border-gray-600'
            } hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors`}
          >
            All
          </button>
          
          {uniqueGenres.map(genre => (
            <button
              key={genre}
              onClick={() => handleFilterChange(genre)}
              className={`px-3 py-1 text-sm rounded border ${
                filter === genre 
                  ? 'bg-black dark:bg-gray-700 text-white' 
                  : 'bg-white dark:bg-gray-800 text-black dark:text-white border-gray-300 dark:border-gray-600'
              } hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors`}
            >
              {genre}
            </button>
          ))}
        </div>
      )}
      
      <div className="bookrec-recommendations-grid grid grid-cols-1 md:grid-cols-2 gap-4">
        {filteredBooks.map((book, index) => (
          <div 
            key={`book-${index}`}
            className={`transform transition-opacity duration-500 ease-in-out ${visibleBooks.includes(index) ? 'opacity-100' : 'opacity-0'}`}
            style={{ transitionDelay: `${index * 100}ms` }}
          >
            <RecommendationCard book={book} ctaVariation={ctaVariation} />
          </div>
        ))}
      </div>
      
      {concludingQuestion && (
        <div className="bookrec-recommendations-conclusion mt-6">
          <p className="italic text-gray-700 dark:text-gray-300">{concludingQuestion}</p>
        </div>
      )}
      
      {/* Email PDF Form - only show if we have recommendations and sessionId */}
      {recommendations && recommendations.length > 0 && sessionId && (
        <EmailPdfForm 
          sessionId={sessionId}
          onSuccess={(data) => console.log('Email sent successfully', data)}
          onError={(error) => console.error('Email error', error)}
        />
      )}
    </div>
  );
}, (prevProps, nextProps) => {
  // Only re-render if essential props change
  if (!prevProps.recommendations && !nextProps.recommendations) return true;
  if (!prevProps.recommendations || !nextProps.recommendations) return false;
  if (prevProps.recommendations.length !== nextProps.recommendations.length) return false;
  if (prevProps.ctaVariation !== nextProps.ctaVariation) return false;
  if (prevProps.sessionId !== nextProps.sessionId) return false;
  
  // Deep comparison not needed for every render - trust React's reconciliation
  return true;
});

export default RecommendationList;