import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import RecommendationCard from '../RecommendationCard';

// Mock modules and browser APIs
global.fetch = jest.fn();
global.window = Object.create(window);
Object.defineProperty(window, 'open', { value: jest.fn() });

describe('RecommendationCard Component', () => {
  // Sample book data for testing
  const sampleBook = {
    title: 'Test Book Title',
    author: 'Test Author',
    description: 'This is a test book description.',
    coverUrl: 'https://example.com/cover.jpg',
    amazonLink: 'https://amazon.com/dp/test123?tag=test-tag',
    publishDate: '2023',
    publisher: 'Test Publisher',
    isbn: '1234567890',
    source: 'google_books'
  };
  
  // Mock bookrec_ajax_data that would be injected by WordPress
  beforeEach(() => {
    window.bookrec_ajax_data = {
      ajax_url: 'https://example.com/wp-admin/admin-ajax.php',
      click_nonce: 'test_nonce',
      rest_url: 'https://example.com/wp-json/',
      settings: {
        ab_test_cta: true
      }
    };
    
    localStorage.setItem = jest.fn();
    localStorage.getItem = jest.fn().mockReturnValue('test-session-id');
    
    // Reset mocks
    global.fetch.mockReset();
    window.open.mockReset();
    fetch.mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({ success: true })
    });
  });
  
  test('renders book details correctly', () => {
    render(<RecommendationCard book={sampleBook} ctaVariation="A" sessionId="test-session" />);
    
    // Check if title and author are displayed
    expect(screen.getByText('Test Book Title')).toBeInTheDocument();
    expect(screen.getByText(/test author/i)).toBeInTheDocument();
    
    // Check if description is displayed
    expect(screen.getByText(sampleBook.description)).toBeInTheDocument();
    
    // Check if metadata is displayed
    expect(screen.getByText(/published: 2023/i)).toBeInTheDocument();
    expect(screen.getByText(/publisher: test publisher/i)).toBeInTheDocument();
    expect(screen.getByText(/isbn: 1234567890/i)).toBeInTheDocument();
    
    // Check if cover image exists with alt text
    const coverImage = screen.getByAltText(/cover of test book title/i);
    expect(coverImage).toBeInTheDocument();
    expect(coverImage).toHaveAttribute('src', sampleBook.coverUrl);
  });
  
  test('renders Amazon link with correct CTA text for Variation A', () => {
    render(<RecommendationCard book={sampleBook} ctaVariation="A" sessionId="test-session" />);
    
    const amazonLink = screen.getByText('View Price on Amazon');
    expect(amazonLink).toBeInTheDocument();
    expect(amazonLink).toHaveAttribute('href', sampleBook.amazonLink);
  });
  
  test('renders Amazon link with correct CTA text for Variation B', () => {
    render(<RecommendationCard book={sampleBook} ctaVariation="B" sessionId="test-session" />);
    
    const amazonLink = screen.getByText('Learn More & Buy');
    expect(amazonLink).toBeInTheDocument();
    expect(amazonLink).toHaveAttribute('href', sampleBook.amazonLink);
  });
  
  test('tracks clicks on Amazon links', async () => {
    render(<RecommendationCard book={sampleBook} ctaVariation="A" sessionId="test-session" />);
    
    // Get Amazon link and click it
    const amazonLink = screen.getByText('View Price on Amazon');
    fireEvent.click(amazonLink);
    
    // Verify fetch was called to track the click
    expect(fetch).toHaveBeenCalledTimes(1);
    expect(fetch.mock.calls[0][0]).toBe(window.bookrec_ajax_data.ajax_url);
    
    // Wait for the async operations to complete
    await new Promise(resolve => setTimeout(resolve, 0));
    
    // Verify window.open was called to navigate to Amazon
    expect(window.open).toHaveBeenCalledWith(sampleBook.amazonLink, '_blank');
  });
  
  test('renders share button', () => {
    render(<RecommendationCard book={sampleBook} ctaVariation="A" sessionId="test-session" />);
    
    const shareButton = screen.getByText('Share');
    expect(shareButton).toBeInTheDocument();
  });
  
  test('shows loading state when sharing', () => {
    render(<RecommendationCard book={sampleBook} ctaVariation="A" sessionId="test-session" />);
    
    // Mock the fetch for the share endpoint to delay
    global.fetch.mockImplementationOnce(() => new Promise(resolve => {
      setTimeout(() => {
        resolve({
          json: () => Promise.resolve({ success: true, share_url: 'https://example.com/share/123' })
        });
      }, 100);
    }));
    
    // Click the share button
    const shareButton = screen.getByText('Share');
    fireEvent.click(shareButton);
    
    // Check if the button shows loading state
    expect(screen.getByText('Sharing...')).toBeInTheDocument();
  });
  
  test('handles share functionality', async () => {
    // Mock the navigator.share API
    const originalNavigator = global.navigator;
    const mockShare = jest.fn().mockResolvedValue(true);
    
    global.navigator = {
      ...originalNavigator,
      share: mockShare
    };
    
    // Mock the share API response
    global.fetch.mockResolvedValueOnce({
      json: () => Promise.resolve({ 
        success: true, 
        share_url: 'https://example.com/share/123' 
      })
    });
    
    render(<RecommendationCard book={sampleBook} ctaVariation="A" sessionId="test-session" />);
    
    // Click the share button
    const shareButton = screen.getByText('Share');
    fireEvent.click(shareButton);
    
    // Wait for the async operations to complete
    await new Promise(resolve => setTimeout(resolve, 0));
    
    // Verify fetch was called to get the share URL
    expect(fetch).toHaveBeenCalledTimes(1);
    
    // Verify navigator.share was called with the right data
    expect(mockShare).toHaveBeenCalledWith({
      title: `Book Recommendation: Test Book Title`,
      text: `Check out this book recommendation: Test Book Title by Test Author`,
      url: 'https://example.com/share/123'
    });
    
    // Restore original navigator
    global.navigator = originalNavigator;
  });
  
  test('handles share error', async () => {
    // Mock the share API response with an error
    global.fetch.mockResolvedValueOnce({
      json: () => Promise.resolve({ 
        success: false, 
        message: 'Share error' 
      })
    });
    
    render(<RecommendationCard book={sampleBook} ctaVariation="A" sessionId="test-session" />);
    
    // Click the share button
    const shareButton = screen.getByText('Share');
    fireEvent.click(shareButton);
    
    // Wait for the async operations to complete
    await new Promise(resolve => setTimeout(resolve, 0));
    
    // Verify error message is displayed
    expect(screen.getByText('Share error')).toBeInTheDocument();
  });
  
  test('falls back to no cover UI when coverUrl is missing', () => {
    const bookWithoutCover = {
      ...sampleBook,
      coverUrl: ''
    };
    
    render(<RecommendationCard book={bookWithoutCover} ctaVariation="A" sessionId="test-session" />);
    
    // Check if the "No Cover Available" placeholder is shown
    expect(screen.getByText('No Cover Available')).toBeInTheDocument();
  });
});