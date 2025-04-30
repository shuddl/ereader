import React, { useState, useCallback, memo } from 'react';

/**
 * Displays a single book recommendation card
 * Memoized to prevent unnecessary re-renders
 */
const RecommendationCard = memo(({ book, ctaVariation, sessionId }) => {
  // Local state for sharing functionality
  const [isSharing, setIsSharing] = useState(false);
  const [shareUrl, setShareUrl] = useState('');
  const [shareError, setShareError] = useState('');
  
  /**
   * Track Amazon link click and then redirect
   * @param {Event} e Click event
   */
  const trackAmazonClick = useCallback((e) => {
    e.preventDefault(); // Prevent default navigation
    
    // Get AJAX URL and nonces from global variables
    const ajaxUrl = window.bookrec_ajax_data?.ajax_url;
    const clickNonce = window.bookrec_ajax_data?.click_nonce;
    const sessionId = localStorage.getItem('bookrec_session_id') || '';
    
    // Use the passed ctaVariation from the session state if available
    // Otherwise, use random A/B test if enabled in settings
    let usedCtaVariation = ctaVariation;
    if (!usedCtaVariation) {
      const abTestEnabled = window.bookrec_ajax_data?.settings?.ab_test_cta || false;
      usedCtaVariation = abTestEnabled ? (Math.random() > 0.5 ? 'A' : 'B') : null;
    }
    
    // Create form data
    const formData = new FormData();
    formData.append('action', 'bookrec_track_click');
    formData.append('nonce', clickNonce);
    formData.append('session_id', sessionId);
    formData.append('title', title || '');
    formData.append('author', author || '');
    formData.append('url', amazonLink || '');
    formData.append('cta_variation', usedCtaVariation || '');
    
    // If we have the required tracking info, track the click
    if (ajaxUrl && clickNonce && amazonLink) {
      // Send tracking request
      fetch(ajaxUrl, {
        method: 'POST',
        body: formData,
      })
      .then(response => {
        // After tracking, redirect user to Amazon regardless of tracking success
        window.open(amazonLink, '_blank');
      })
      .catch(error => {
        console.error('Error tracking click:', error);
        // Still open Amazon link even if tracking fails
        window.open(amazonLink, '_blank');
      });
    } else {
      // If tracking isn't possible, just navigate directly
      window.open(amazonLink, '_blank');
    }
  }, [amazonLink, title, author]);
  
  /**
   * Generate a shareable link for this book
   */
  const generateShareLink = useCallback(async () => {
    setIsSharing(true);
    setShareError('');
    
    try {
      // REST API endpoint URL
      const restUrl = window.bookrec_ajax_data?.rest_url || '/wp-json/';
      const shareEndpoint = `${restUrl}bookrec/v1/share`;
      
      // Prepare book data for sharing
      const bookData = {
        title: title || '',
        author: author || '',
        cover: coverUrl || '',
        description: description || '',
        isbn: isbn || ''
      };
      
      // Make the API request
      const response = await fetch(shareEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(bookData)
      });
      
      const data = await response.json();
      
      if (data.success && data.share_url) {
        setShareUrl(data.share_url);
        shareBook(data.share_url);
      } else {
        setShareError(data.message || 'Failed to generate share link');
      }
    } catch (error) {
      console.error('Error generating share link:', error);
      setShareError('Error creating share link');
    } finally {
      setIsSharing(false);
    }
  }, [title, author, coverUrl, description, isbn]);
  
  /**
   * Share book using Web Share API with fallback
   */
  const shareBook = useCallback((url) => {
    // Text to share
    const shareText = `Check out this book recommendation: ${title} by ${author}`;
    
    // Try to use Web Share API if available
    if (navigator.share) {
      navigator.share({
        title: `Book Recommendation: ${title}`,
        text: shareText,
        url: url
      })
      .catch(error => {
        console.error('Error sharing:', error);
        // Fallback to clipboard copy on share error
        copyToClipboard(url);
      });
    } else {
      // Fallback to clipboard copy
      copyToClipboard(url);
    }
  }, [title, author, copyToClipboard]);
  
  /**
   * Copy text to clipboard with user feedback
   */
  const copyToClipboard = useCallback((text) => {
    // Create temporary textarea element
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'absolute';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    
    // Select and copy text
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
    
    // Show feedback to user
    alert('Share link copied to clipboard!');
  }, []);
  const {
    title,
    author,
    description,
    coverUrl,
    amazonLink,
    publishDate,
    publisher,
    isbn,
    source
  } = book;

  return (
    <div className="bookrec-recommendation-card mb-4 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-shadow bg-white dark:bg-gray-800">
      <div className="bookrec-recommendation-card-inner flex flex-col md:flex-row">
        {/* Cover image */}
        <div className="bookrec-recommendation-cover w-full md:w-1/4 max-w-[150px] p-2 flex items-center justify-center bg-gray-50 dark:bg-gray-900">
          {coverUrl ? (
            <img 
              src={coverUrl} 
              alt={`Cover of ${title}`} 
              className="w-full h-auto object-contain"
            />
          ) : (
            <div className="w-full h-[200px] bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-gray-500 dark:text-gray-400 text-sm">
              No Cover Available
            </div>
          )}
        </div>
        
        {/* Book details */}
        <div className="bookrec-recommendation-details flex-1 p-4">
          <h3 className="text-lg font-bold mb-1 text-gray-900 dark:text-white">{title}</h3>
          <p className="text-sm text-gray-700 dark:text-gray-300 mb-3">by {author}</p>
          
          <div className="bookrec-recommendation-description mb-4">
            <p className="text-sm text-gray-800 dark:text-gray-200">{description || 'No description available.'}</p>
          </div>
          
          <div className="bookrec-recommendation-metadata text-xs text-gray-500 dark:text-gray-400 mb-4">
            {publishDate && <span className="mr-3">Published: {publishDate}</span>}
            {publisher && <span className="mr-3">Publisher: {publisher}</span>}
            {isbn && <span>ISBN: {isbn}</span>}
          </div>
          
          <div className="bookrec-recommendation-actions flex flex-wrap gap-2">
            {amazonLink && (
              <div className="bookrec-recommendation-action">
                <a 
                  href={amazonLink} 
                  target="_blank" 
                  rel="noopener noreferrer" 
                  className={`inline-block px-4 py-2 text-sm rounded transition-colors ${
                    ctaVariation === 'B' 
                      ? 'bg-blue-600 dark:bg-blue-700 text-white hover:bg-blue-700 dark:hover:bg-blue-800' // Variation B style
                      : 'bg-black dark:bg-gray-800 text-white hover:bg-gray-800 dark:hover:bg-gray-700'     // Variation A style (default)
                  }`}
                  data-book-id={title}
                  data-cta-variation={ctaVariation}
                  onClick={trackAmazonClick}
                >
                  {ctaVariation === 'B' ? 'Learn More & Buy' : 'View Price on Amazon'}
                </a>
              </div>
            )}
            
            <div className="bookrec-recommendation-share">
              <button
                onClick={generateShareLink}
                disabled={isSharing}
                className="inline-block px-4 py-2 text-sm rounded transition-colors bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 border border-gray-300 dark:border-gray-600"
                aria-label="Share this book recommendation"
              >
                {isSharing ? 'Sharing...' : 'Share'}
              </button>
              {shareError && <p className="text-red-600 dark:text-red-400 text-xs mt-1">{shareError}</p>}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}, (prevProps, nextProps) => {
  // Custom comparison function for memo
  // Only re-render if essential props change
  return (
    prevProps.book.title === nextProps.book.title &&
    prevProps.book.author === nextProps.book.author &&
    prevProps.book.coverUrl === nextProps.book.coverUrl &&
    prevProps.ctaVariation === nextProps.ctaVariation
  );
});

export default RecommendationCard;