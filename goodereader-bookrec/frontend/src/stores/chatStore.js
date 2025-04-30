import { create } from 'zustand';

/**
 * Chat store with Zustand for centralized state management
 * Contains all state related to the chat interface and recommendations
 */
const useChatStore = create((set) => ({
  // Session state
  sessionId: '',
  isInitialized: false,
  
  // UI state
  isLoading: true,
  isTyping: false,
  error: null,
  
  // Message state
  messages: [],
  
  // A/B testing
  ctaVariation: null,
  
  // Email PDF state
  emailSent: false,
  isEmailLoading: false,
  emailError: null,
  
  // Conversation context (filled slots, etc.)
  conversationContext: {
    slots: {},
  },
  
  // Actions
  
  /**
   * Initialize session with data from server
   */
  initializeSession: (sessionData) => set({
    sessionId: sessionData.sessionId,
    messages: sessionData.history || [],
    isInitialized: true,
    isLoading: false,
    ctaVariation: sessionData.ctaVariation || null,
    error: null,
  }),
  
  /**
   * Update CTA variation
   */
  updateCtaVariation: (variation) => set({
    ctaVariation: variation,
  }),
  
  /**
   * Add a user message to the conversation
   */
  addUserMessage: (message) => set((state) => ({
    messages: [
      ...state.messages, 
      {
        role: 'user',
        content: message,
        timestamp: Date.now() / 1000,
      }
    ],
    isTyping: true,
    error: null,
  })),
  
  /**
   * Add an assistant message to the conversation
   */
  addAssistantMessage: (messageData) => set((state) => ({
    messages: [
      ...state.messages, 
      {
        role: 'assistant',
        content: messageData.message,
        timestamp: Date.now() / 1000,
        // Add recommendation data if available
        ...(messageData.recommendations && {
          recommendations: messageData.recommendations,
          explanatoryText: messageData.explanatoryText,
          concludingQuestion: messageData.concludingQuestion
        })
      }
    ],
    isTyping: false,
    isLoading: false,
  })),
  
  /**
   * Add a system message to the conversation
   */
  addSystemMessage: (message) => set((state) => ({
    messages: [
      ...state.messages, 
      {
        role: 'system',
        content: message,
        timestamp: Date.now() / 1000,
      }
    ],
  })),
  
  /**
   * Set an error message
   */
  setError: (message) => set({
    error: message,
    isLoading: false,
    isTyping: false,
  }),
  
  /**
   * Clear error message
   */
  clearError: () => set({
    error: null,
  }),
  
  /**
   * Set loading state
   */
  startLoading: () => set({
    isLoading: true,
    error: null,
  }),
  
  /**
   * Stop loading state
   */
  stopLoading: () => set({
    isLoading: false,
    isTyping: false,
  }),
  
  /**
   * Update conversation context
   */
  updateConversationContext: (context) => set((state) => ({
    conversationContext: {
      ...state.conversationContext,
      ...context,
    },
  })),
  
  /**
   * Set email sending state
   */
  setEmailLoading: (isLoading) => set({
    isEmailLoading: isLoading,
  }),
  
  /**
   * Update email status
   */
  setEmailSent: (sent) => set({
    emailSent: sent,
  }),
  
  /**
   * Set email error
   */
  setEmailError: (error) => set({
    emailError: error,
  }),
}));

export default useChatStore;