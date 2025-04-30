import React from 'react';
import { render, screen } from '@testing-library/react';
import ChatMessage from '../ChatMessage';

// Add a debug helper
const logHtml = (element) => {
  if (element) console.log(element.outerHTML);
  else console.log('Element not found');
};

// Mock the RecommendationList component
jest.mock('../RecommendationList', () => {
  return function MockRecommendationList({ recommendations }) {
    return (
      <div data-testid="mock-recommendation-list">
        {recommendations.length} recommendations
      </div>
    );
  };
});

describe('ChatMessage Component', () => {
  test('renders user message correctly', () => {
    const userMessage = {
      role: 'user',
      content: 'Hello, I need book recommendations',
    };
    
    const { container } = render(<ChatMessage message={userMessage} />);
    
    // Check if message content is rendered
    expect(screen.getByText('Hello, I need book recommendations')).toBeInTheDocument();
    
    // Check if the component has the user message class
    expect(container.innerHTML).toContain('bookrec-message-user');
  });
  
  test('renders assistant message correctly', () => {
    const assistantMessage = {
      role: 'assistant',
      content: 'Here are some books you might enjoy.',
    };
    
    const { container } = render(<ChatMessage message={assistantMessage} />);
    
    // Check if message content is rendered
    expect(screen.getByText('Here are some books you might enjoy.')).toBeInTheDocument();
    
    // Check if assistant message has correct styling
    expect(container.innerHTML).toContain('bookrec-message-ai');
  });
  
  test('renders system message correctly', () => {
    const systemMessage = {
      role: 'system',
      content: 'Welcome to Book Recommender!',
    };
    
    const { container } = render(<ChatMessage message={systemMessage} />);
    
    // Check if message content is rendered
    expect(screen.getByText('Welcome to Book Recommender!')).toBeInTheDocument();
    
    // Check if system message has correct styling
    expect(container.innerHTML).toContain('bookrec-message-system');
  });
  
  test('handles markdown-style bold formatting in content', () => {
    const messageWithBold = {
      role: 'assistant',
      content: 'I recommend **The Great Gatsby** by F. Scott Fitzgerald',
    };
    
    render(<ChatMessage message={messageWithBold} />);
    
    // Check if the HTML was rendered with strong tags
    const messageDiv = screen.getByText(/The Great Gatsby/).closest('div');
    expect(messageDiv.innerHTML).toContain('<strong>The Great Gatsby</strong>');
  });
  
  test('renders recommendations when present', () => {
    const messageWithRecommendations = {
      role: 'assistant',
      content: 'Here are my recommendations for you:',
      recommendations: [
        { title: 'Book 1', author: 'Author 1' },
        { title: 'Book 2', author: 'Author 2' }
      ],
    };
    
    render(<ChatMessage message={messageWithRecommendations} />);
    
    // Check if text content is rendered
    expect(screen.getByText('Here are my recommendations for you:')).toBeInTheDocument();
    
    // Check if recommendation list component is rendered
    expect(screen.getByTestId('mock-recommendation-list')).toBeInTheDocument();
    expect(screen.getByText('2 recommendations')).toBeInTheDocument();
  });
});