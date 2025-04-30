import React from 'react';
import RecommendationList from './RecommendationList';

/**
 * Renders a single chat message
 */
const ChatMessage = ({ message, isLastMessage, ctaVariation, sessionId }) => {
  // Determine message type styling
  const isUser = message.role === 'user';
  const isSystem = message.role === 'system';
  
  // Check if message has recommendations to adjust styling
  const hasRecommendations = message.recommendations && 
                            Array.isArray(message.recommendations) && 
                            message.recommendations.length > 0;
                           
  // Apply different styles based on message sender
  const messageClasses = [
    'bookrec-message mb-3 p-3 rounded-lg animate-fade-in transition-opacity',
    // Adjust max width when recommendations are present
    hasRecommendations ? 'max-w-full w-full' : 'max-w-[80%]',
    isUser ? 'bookrec-message-user ml-auto bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100' : '',
    message.role === 'assistant' ? 'bookrec-message-ai mr-auto bg-black dark:bg-gray-900 text-white' : '',
    // If assistant message has recommendations, use a lighter background
    (message.role === 'assistant' && hasRecommendations) ? 'bg-gray-800 dark:bg-black' : '',
    isSystem ? 'bookrec-message-system mx-auto bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 italic text-center' : '',
    isLastMessage ? 'bookrec-message-last' : ''
  ].filter(Boolean).join(' ');

  // Process message content - handle text, recommendations, or other formats
  const renderContent = () => {
    // Check if the message has recommendations attached
    if (message.recommendations && Array.isArray(message.recommendations)) {
      return (
        <>
          <div className="mb-3">{message.content}</div>
          <RecommendationList 
            recommendations={message.recommendations}
            explanatoryText={message.explanatoryText}
            concludingQuestion={message.concludingQuestion}
            ctaVariation={ctaVariation}
            sessionId={sessionId}
          />
        </>
      );
    } 
    // Regular text message
    else if (typeof message.content === 'string') {
      // Convert markdown-style links to actual links
      const linkified = message.content.replace(
        /\*\*(.*?)\*\*/g, 
        '<strong>$1</strong>'
      );
      
      return <div dangerouslySetInnerHTML={{ __html: linkified }} />;
    } 
    // Fallback for any other format
    else {
      return JSON.stringify(message.content);
    }
  };

  return (
    <div className={messageClasses}>
      {renderContent()}
    </div>
  );
};

export default ChatMessage;