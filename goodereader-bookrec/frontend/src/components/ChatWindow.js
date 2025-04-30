import React from 'react';
import MessageList from './MessageList';
import ChatInput from './ChatInput';
import LoadingIndicator from './LoadingIndicator';
import ErrorMessage from './ErrorMessage';
import AuthorSearchBar from './AuthorSearchBar';

/**
 * Main chat window container component
 */
const ChatWindow = ({ 
  messages, 
  inputValue, 
  onInputChange, 
  onSendMessage, 
  onAuthorSearch,
  isLoading, 
  error,
  isTyping = false,
  authorSearchEnabled = false,
  genreFilter = '',
  ctaVariation = null,
  sessionId = null
}) => {
  return (
    <div className="bookrec-chat-window flex flex-col h-full">
      <div className="bookrec-chat-header mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
        <div className="flex justify-between items-center">
          <div>
            <h3 className="text-lg font-bold text-gray-900 dark:text-gray-50">Book Recommendation Chatbot</h3>
            <p className="text-sm text-gray-600 dark:text-gray-400">
              {genreFilter ? `Focused on ${genreFilter} books` : 'Ask me about books you might enjoy!'}
            </p>
          </div>
          
          {/* Author search button will only show if enabled */}
          <AuthorSearchBar 
            onSearch={onAuthorSearch} 
            isActive={authorSearchEnabled}
          />
        </div>
      </div>
      
      <div className="bookrec-chat-body flex-grow overflow-hidden relative mb-4">
        <MessageList messages={messages} ctaVariation={ctaVariation} sessionId={sessionId} />
        
        {isTyping && (
          <div className="absolute bottom-0 left-0 w-full pb-2">
            <LoadingIndicator message="Thinking..." type="typing" />
          </div>
        )}
      </div>
      
      {error && <ErrorMessage message={error} />}
      
      <div className="bookrec-chat-footer mt-auto">
        <ChatInput 
          value={inputValue}
          onChange={onInputChange}
          onSubmit={onSendMessage}
          isLoading={isLoading}
          placeholder={genreFilter 
            ? `Ask about ${genreFilter} books or any other genres...` 
            : "Tell me about books you've enjoyed..."
          }
        />
      </div>
    </div>
  );
};

export default ChatWindow;