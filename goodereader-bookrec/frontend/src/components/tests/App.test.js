import React from 'react';
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import App from '../../App';

// Mock ChatStore to better control the state
jest.mock('../../stores/chatStore', () => {
  // Create a basic mock implementation
  const mockStore = {
    sessionId: '',
    messages: [],
    isLoading: false,
    isTyping: false,
    isInitialized: false,
    error: null,
    ctaVariation: 'A',
    
    // Mock actions
    initializeSession: jest.fn(),
    addUserMessage: jest.fn(),
    addAssistantMessage: jest.fn(),
    setError: jest.fn(),
    clearError: jest.fn(),
    startLoading: jest.fn(),
    stopLoading: jest.fn(),
    updateConversationContext: jest.fn(),
    updateCtaVariation: jest.fn()
  };
  
  // Create a function that returns our mock store
  return jest.fn(() => mockStore);
});

// Mock child components to simplify testing
jest.mock('../ChatWindow', () => {
  return function MockChatWindow(props) {
    return (
      <div data-testid="chat-window">
        <div data-testid="messages-count">{props.messages.length} messages</div>
        <button 
          data-testid="send-button" 
          onClick={() => props.onSendMessage()} 
          disabled={props.isLoading}
        >
          Send
        </button>
        <input 
          data-testid="chat-input" 
          value={props.inputValue} 
          onChange={props.onInputChange} 
          disabled={props.isLoading}
        />
        {props.error && <div data-testid="error-message">{props.error}</div>}
        {props.isLoading && <div data-testid="loading-indicator">Loading...</div>}
      </div>
    );
  };
});

// Mock LoadingIndicator
jest.mock('../LoadingIndicator', () => {
  return function MockLoadingIndicator({ message }) {
    return <div data-testid="loading-indicator">{message}</div>;
  };
});

describe('App Component', () => {
  // Mock fetch for all tests
  const mockFetchPromise = Promise.resolve({
    json: () => Promise.resolve({
      success: true,
      data: {
        session_id: 'test-session-id',
        history: [
          {
            role: 'assistant',
            content: 'Hello! How can I help you find your next favorite book?'
          }
        ],
        cta_variation: 'A'
      }
    })
  });
  
  beforeEach(() => {
    global.fetch = jest.fn().mockImplementation(() => mockFetchPromise);
    global.localStorage = {
      getItem: jest.fn(),
      setItem: jest.fn()
    };
  });
  
  afterEach(() => {
    jest.clearAllMocks();
  });
  
  test('renders loading state before initialization', async () => {
    // Simulate no existing session
    global.localStorage.getItem.mockReturnValue(null);
    
    // Initial state where isInitialized is false and isLoading is true
    const useChatStore = require('../../stores/chatStore');
    const storeInstance = useChatStore();
    storeInstance.isInitialized = false;
    storeInstance.isLoading = true;
    
    render(<App containerId="test-container" settings={{ ajax_url: 'test-url', nonce: 'test-nonce' }} />);
    
    // Should show the loading indicator
    expect(screen.getByTestId('loading-indicator')).toHaveTextContent('Starting book recommendation engine...');
  });
  
  test('initializes a new session when no stored session exists', async () => {
    // Simulate no existing session
    global.localStorage.getItem.mockReturnValue(null);
    
    // Make store return initialized state after init
    const useChatStore = require('../../stores/chatStore');
    const storeInstance = useChatStore();
    storeInstance.isInitialized = true;
    storeInstance.isLoading = false;
    
    render(<App containerId="test-container" settings={{ ajax_url: 'test-url', nonce: 'test-nonce' }} />);
    
    // Verify that fetch was called to initialize a session
    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledTimes(1);
    });
    
    const fetchCall = global.fetch.mock.calls[0];
    expect(fetchCall[0]).toBe('test-url');
    expect(fetchCall[1].body.toString()).toContain('bookrec_init_session');
  });
  
  test('uses existing session from localStorage if available', async () => {
    // Simulate existing session
    global.localStorage.getItem.mockReturnValueOnce('existing-session-id');
    
    // Make store return initialized state
    const useChatStore = require('../../stores/chatStore');
    const storeInstance = useChatStore();
    storeInstance.isInitialized = true;
    storeInstance.isLoading = false;
    
    render(<App containerId="test-container" settings={{ ajax_url: 'test-url', nonce: 'test-nonce' }} />);
    
    // Verify that initializeSession was called with the existing session ID
    expect(storeInstance.initializeSession).toHaveBeenCalled();
    
    // Should not make a fetch request to initialize a new session
    await waitFor(() => {
      expect(global.fetch).not.toHaveBeenCalled();
    });
  });
  
  test('handles user sending a message', async () => {
    // Prepare mock chat store
    const useChatStore = require('../../stores/chatStore');
    const storeInstance = useChatStore();
    storeInstance.isInitialized = true;
    storeInstance.isLoading = false;
    storeInstance.sessionId = 'test-session-id';
    
    // Mock successful message response
    global.fetch.mockImplementationOnce(() => Promise.resolve({
      json: () => Promise.resolve({
        success: true,
        data: {
          message: 'I can recommend some books for you!',
          recommendations: [
            { title: 'Book 1', author: 'Author 1' },
            { title: 'Book 2', author: 'Author 2' }
          ]
        }
      })
    }));
    
    const { getByTestId } = render(
      <App containerId="test-container" settings={{ ajax_url: 'test-url', nonce: 'test-nonce' }} />
    );
    
    // Simulate typing a message
    const input = getByTestId('chat-input');
    fireEvent.change(input, { target: { value: 'I like fantasy books' } });
    
    // Send the message
    const sendButton = getByTestId('send-button');
    fireEvent.click(sendButton);
    
    // Should start loading
    expect(storeInstance.startLoading).toHaveBeenCalled();
    expect(storeInstance.addUserMessage).toHaveBeenCalledWith('I like fantasy books');
    
    // Verify that fetch was called to send the message
    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledTimes(1);
    });
    
    // Check the fetch call details
    const fetchCall = global.fetch.mock.calls[0];
    expect(fetchCall[0]).toBe('test-url');
    expect(fetchCall[1].body.toString()).toContain('bookrec_chat');
    expect(fetchCall[1].body.toString()).toContain('I like fantasy books');
    
    // After response, should add assistant message and stop loading
    await waitFor(() => {
      expect(storeInstance.addAssistantMessage).toHaveBeenCalledWith({
        message: 'I can recommend some books for you!',
        recommendations: expect.any(Array),
        explanatoryText: '',
        concludingQuestion: ''
      });
      expect(storeInstance.stopLoading).toHaveBeenCalled();
    });
  });
  
  test('handles API errors when sending message', async () => {
    // Prepare mock chat store
    const useChatStore = require('../../stores/chatStore');
    const storeInstance = useChatStore();
    storeInstance.isInitialized = true;
    storeInstance.isLoading = false;
    storeInstance.sessionId = 'test-session-id';
    
    // Mock error response
    global.fetch.mockImplementationOnce(() => Promise.resolve({
      json: () => Promise.resolve({
        success: false,
        data: {
          message: 'API error occurred'
        }
      })
    }));
    
    const { getByTestId } = render(
      <App containerId="test-container" settings={{ ajax_url: 'test-url', nonce: 'test-nonce' }} />
    );
    
    // Simulate typing a message
    const input = getByTestId('chat-input');
    fireEvent.change(input, { target: { value: 'I like fantasy books' } });
    
    // Send the message
    const sendButton = getByTestId('send-button');
    fireEvent.click(sendButton);
    
    // After error response, should set error and stop loading
    await waitFor(() => {
      expect(storeInstance.setError).toHaveBeenCalled();
      expect(storeInstance.stopLoading).toHaveBeenCalled();
    });
  });
  
  test('handles network errors when sending message', async () => {
    // Prepare mock chat store
    const useChatStore = require('../../stores/chatStore');
    const storeInstance = useChatStore();
    storeInstance.isInitialized = true;
    storeInstance.isLoading = false;
    storeInstance.sessionId = 'test-session-id';
    
    // Mock network error
    global.fetch.mockImplementationOnce(() => Promise.reject(new Error('Network error')));
    
    const { getByTestId } = render(
      <App containerId="test-container" settings={{ ajax_url: 'test-url', nonce: 'test-nonce' }} />
    );
    
    // Simulate typing a message
    const input = getByTestId('chat-input');
    fireEvent.change(input, { target: { value: 'I like fantasy books' } });
    
    // Send the message
    const sendButton = getByTestId('send-button');
    fireEvent.click(sendButton);
    
    // After network error, should set error and stop loading
    await waitFor(() => {
      expect(storeInstance.setError).toHaveBeenCalled();
      expect(storeInstance.stopLoading).toHaveBeenCalled();
    });
  });
  
  test('handles author search', async () => {
    // Prepare mock chat store
    const useChatStore = require('../../stores/chatStore');
    const storeInstance = useChatStore();
    storeInstance.isInitialized = true;
    storeInstance.isLoading = false;
    storeInstance.sessionId = 'test-session-id';
    
    // Mock successful author search response
    global.fetch.mockImplementationOnce(() => Promise.resolve({
      json: () => Promise.resolve({
        success: true,
        data: {
          message: 'Here are books by the author you requested:',
          recommendations: [
            { title: 'Author Book 1', author: 'Requested Author' },
            { title: 'Author Book 2', author: 'Requested Author' }
          ]
        }
      })
    }));
    
    render(
      <App 
        containerId="test-container" 
        settings={{ 
          ajax_url: 'test-url', 
          nonce: 'test-nonce',
          attributes: { author_search: 'true' }
        }} 
      />
    );
    
    // Get reference to the handleAuthorSearch method
    const appInstance = screen.getByTestId('chat-window');
    
    // We need to extract the onAuthorSearch prop from ChatWindow
    // This requires a slight modification to our test approach
    // Let's use a simulated onAuthorSearch call
    const mockChatWindow = require('../ChatWindow').mock.calls;
    const onAuthorSearchProp = mockChatWindow[mockChatWindow.length - 1][0].onAuthorSearch;
    
    // Call the handler with an author name
    act(() => {
      onAuthorSearchProp('Requested Author');
    });
    
    // Should start loading and add user message
    expect(storeInstance.startLoading).toHaveBeenCalled();
    expect(storeInstance.addUserMessage).toHaveBeenCalledWith('Show me books by Requested Author');
    
    // Verify that fetch was called for author search
    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledTimes(1);
    });
    
    // Check the fetch call details
    const fetchCall = global.fetch.mock.calls[0];
    expect(fetchCall[0]).toBe('test-url');
    expect(fetchCall[1].body.toString()).toContain('bookrec_chat');
    expect(fetchCall[1].body.toString()).toContain('author_search=true');
    expect(fetchCall[1].body.toString()).toContain('author_name=Requested+Author');
    
    // After response, should add assistant message with recommendations
    await waitFor(() => {
      expect(storeInstance.addAssistantMessage).toHaveBeenCalledWith({
        message: 'Here are books by the author you requested:',
        recommendations: expect.any(Array),
        explanatoryText: '',
        concludingQuestion: ''
      });
      expect(storeInstance.stopLoading).toHaveBeenCalled();
    });
  });
});