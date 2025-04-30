import React, { useRef, useEffect } from 'react';
import ChatMessage from './ChatMessage';

/**
 * Renders a list of chat messages with auto-scrolling
 */
const MessageList = ({ messages, ctaVariation, sessionId }) => {
  const messagesEndRef = useRef(null);

  // Auto-scroll to bottom when messages change
  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  return (
    <div className="bookrec-message-list overflow-y-auto h-full pr-1">
      {messages.length === 0 ? (
        <div className="text-center text-gray-500 italic py-4">
          <p>Start a conversation to get book recommendations</p>
        </div>
      ) : (
        messages.map((message, index) => {
          // Check if message has recommendations to adjust container width
          const hasRecommendations = message.recommendations && 
                                   Array.isArray(message.recommendations) && 
                                   message.recommendations.length > 0;
          
          return (
            <div 
              key={`${message.role}-${index}`}
              className={hasRecommendations ? 'w-full max-w-full' : ''}
            >
              <ChatMessage 
                message={message}
                isLastMessage={index === messages.length - 1}
                ctaVariation={ctaVariation}
                sessionId={sessionId}
              />
            </div>
          );
        })
      )}
      <div ref={messagesEndRef} className="h-1" />
    </div>
  );
};

export default MessageList;