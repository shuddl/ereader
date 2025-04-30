import React, { useState, useEffect, useCallback } from 'react';
import ChatWindow from './components/ChatWindow';
import LoadingIndicator from './components/LoadingIndicator';
import useChatStore from './stores/chatStore';

/**
 * Main App component for the Book Recommender chatbot
 * Uses Zustand for state management
 */
const App = ({ containerId, settings }) => {
  // Local component state
  const [inputValue, setInputValue] = useState('');
  
  // Global zustand state
  const {
    sessionId,
    messages,
    isLoading,
    isTyping,
    isInitialized,
    error,
    ctaVariation,
    // Actions
    initializeSession: storeInitializeSession,
    addUserMessage,
    addAssistantMessage,
    setError,
    clearError,
    startLoading,
    stopLoading,
    updateConversationContext,
    updateCtaVariation
  } = useChatStore();
  
  // Parse container attributes
  const authorSearchEnabled = settings.attributes?.author_search === 'true';
  const genreFilter = settings.attributes?.genre || '';
  
  // Initialize session on component mount
  useEffect(() => {
    if (!settings.ajax_url) {
      setError('AJAX URL not provided. Check plugin configuration.');
      return;
    }
    
    const storedSessionId = localStorage.getItem(`bookrec_session_${containerId}`);
    
    if (storedSessionId) {
      setSessionId(storedSessionId);
    } else {
      initializeSession();
    }
  }, []);
  
  // Set session ID from storage - memoized with useCallback
  const setSessionId = useCallback((sid) => {
    if (!sid) return;
    
    storeInitializeSession({
      sessionId: sid,
      history: [{
        role: 'assistant',
        content: 'Hello! I\'m your book recommendation assistant. What kinds of books do you enjoy reading?',
        timestamp: Date.now() / 1000,
      }]
    });
  }, [storeInitializeSession]);
  
  // Initialize a new session - memoized with useCallback
  const initializeSession = useCallback(async () => {
    try {
      const response = await fetch(settings.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'bookrec_init_session',
          nonce: settings.nonce,
          container_id: containerId,
          genre: genreFilter || '',
        }),
      });
      
      const data = await response.json();
      
      if (data.success) {
        const { session_id, history, welcome_message, cta_variation } = data.data;
        
        storeInitializeSession({
          sessionId: session_id,
          ctaVariation: cta_variation,
          history: history || [{
            role: 'assistant',
            content: welcome_message || 'Hello! How can I help you find your next favorite book?',
            timestamp: Date.now() / 1000,
          }]
        });
        
        // Store in both container-specific and general storage (for tracking purposes)
        localStorage.setItem(`bookrec_session_${containerId}`, session_id);
        localStorage.setItem('bookrec_session_id', session_id);
      } else {
        throw new Error(data.data?.message || 'Failed to initialize chat session');
      }
    } catch (error) {
      console.error('Error initializing session:', error);
      setError('Failed to start chat session. Please refresh the page.');
    }
  }, [storeInitializeSession, setError, settings.ajax_url, settings.nonce, containerId, genreFilter]);
  
  // Handle input change
  const handleInputChange = useCallback((e) => {
    setInputValue(e.target.value);
  }, []);
  
  // Send message - memoized with useCallback
  const handleSendMessage = useCallback(async () => {
    const message = inputValue.trim();
    if (!message || !sessionId || isLoading) return;
    
    // Clear input and add user message to UI immediately
    setInputValue('');
    addUserMessage(message);
    startLoading();
    
    try {
      const response = await fetch(settings.ajax_url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          action: 'bookrec_chat',
          nonce: settings.nonce,
          session_id: sessionId,
          container_id: containerId,
          user_message: message,
        }),
      });
      
      const data = await response.json();
      
      if (data.success) {
        // Prepare payload for assistant message
        const messagePayload = { 
          message: data.data.message 
        };
        
        // Add recommendation data if available
        if (data.data.recommendations) {
          messagePayload.recommendations = data.data.recommendations;
          messagePayload.explanatoryText = data.data.explanatory_text || '';
          messagePayload.concludingQuestion = data.data.concluding_question || '';
        }
        
        // Add assistant message with any recommendations
        addAssistantMessage(messagePayload);
        
        // Update conversation context if provided
        if (data.data.context) {
          updateConversationContext(data.data.context);
        }
        
        // Update CTA variation if provided
        if (data.data.cta_variation) {
          updateCtaVariation(data.data.cta_variation);
        }
      } else {
        throw new Error(data.data?.message || 'Failed to process message');
      }
    } catch (error) {
      console.error('Error sending message:', error);
      setError('Failed to send message. Please try again.');
    } finally {
      stopLoading();
    }
  }, [
    inputValue, 
    sessionId, 
    isLoading, 
    settings.ajax_url, 
    settings.nonce, 
    containerId, 
    addUserMessage,
    addAssistantMessage,
    startLoading,
    stopLoading,
    setError,
    updateConversationContext,
    updateCtaVariation
  ]);
  
  // Handle author search - memoized with useCallback
  const handleAuthorSearch = useCallback((authorName) => {
    if (!authorName || !sessionId || isLoading) return;
    
    // Format author search message
    const searchMessage = `Show me books by ${authorName}`;
    
    // Clear input and add user message to UI immediately
    setInputValue('');
    addUserMessage(searchMessage);
    startLoading();
    
    // Send author search request
    fetch(settings.ajax_url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams({
        action: 'bookrec_chat',
        nonce: settings.nonce,
        session_id: sessionId,
        container_id: containerId,
        user_message: searchMessage,
        author_search: 'true', // Signal backend to use author search mode
        author_name: authorName,
      }),
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Prepare payload for assistant message
        const messagePayload = { 
          message: data.data.message 
        };
        
        // Add recommendation data if available
        if (data.data.recommendations) {
          messagePayload.recommendations = data.data.recommendations;
          messagePayload.explanatoryText = data.data.explanatory_text || '';
          messagePayload.concludingQuestion = data.data.concluding_question || '';
        }
        
        // Add assistant message with any recommendations
        addAssistantMessage(messagePayload);
        
        // Update conversation context if provided
        if (data.data.context) {
          updateConversationContext(data.data.context);
        }
        
        // Update CTA variation if provided
        if (data.data.cta_variation) {
          updateCtaVariation(data.data.cta_variation);
        }
      } else {
        throw new Error(data.data?.message || 'Failed to process author search');
      }
    })
    .catch(error => {
      console.error('Error with author search:', error);
      setError('Failed to search for author. Please try again.');
    })
    .finally(() => {
      stopLoading();
    });
  }, [
    sessionId, 
    isLoading, 
    settings.ajax_url, 
    settings.nonce, 
    containerId, 
    addUserMessage, 
    addAssistantMessage, 
    startLoading, 
    stopLoading, 
    setError,
    updateConversationContext,
    updateCtaVariation
  ]);

  // Render loading state before initialization
  if (!isInitialized && isLoading) {
    return (
      <div className="bookrec-loading-container p-4">
        <LoadingIndicator message="Starting book recommendation engine..." />
      </div>
    );
  }

  return (
    <div className="bookrec-app h-full">
      <ChatWindow
        messages={messages}
        inputValue={inputValue}
        onInputChange={handleInputChange}
        onSendMessage={handleSendMessage}
        onAuthorSearch={handleAuthorSearch}
        isLoading={isLoading}
        isTyping={isTyping}
        error={error}
        onClearError={clearError}
        authorSearchEnabled={authorSearchEnabled}
        genreFilter={genreFilter}
        ctaVariation={ctaVariation}
        sessionId={sessionId}
      />
    </div>
  );
};

export default App;