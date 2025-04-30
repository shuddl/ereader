import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import ChatInput from '../ChatInput';

describe('ChatInput Component', () => {
  test('renders with placeholder text', () => {
    render(<ChatInput 
      value="" 
      onChange={jest.fn()}
      onSubmit={jest.fn()}
      isLoading={false}
      placeholder="Test placeholder"
    />);
    
    expect(screen.getByPlaceholderText('Test placeholder')).toBeInTheDocument();
  });
  
  test('handles input change', () => {
    const mockOnChange = jest.fn();
    
    render(<ChatInput 
      value="" 
      onChange={mockOnChange}
      onSubmit={jest.fn()}
      isLoading={false}
    />);
    
    const input = screen.getByRole('textbox');
    fireEvent.change(input, { target: { value: 'test message' } });
    
    expect(mockOnChange).toHaveBeenCalled();
  });
  
  test('handles form submission', () => {
    const mockOnSubmit = jest.fn();
    
    render(<ChatInput 
      value="test message" 
      onChange={jest.fn()}
      onSubmit={mockOnSubmit}
      isLoading={false}
    />);
    
    const button = screen.getByRole('button', { name: /send/i });
    fireEvent.click(button);
    
    expect(mockOnSubmit).toHaveBeenCalled();
  });
  
  test('disables button when input is empty', () => {
    render(<ChatInput 
      value="" 
      onChange={jest.fn()}
      onSubmit={jest.fn()}
      isLoading={false}
    />);
    
    const button = screen.getByRole('button', { name: /send/i });
    expect(button).toBeDisabled();
  });
  
  test('disables button when loading', () => {
    render(<ChatInput 
      value="test message" 
      onChange={jest.fn()}
      onSubmit={jest.fn()}
      isLoading={true}
    />);
    
    const button = screen.getByRole('button', { name: /sending/i });
    expect(button).toBeDisabled();
  });
  
  test('disables input when loading', () => {
    render(<ChatInput 
      value="test message" 
      onChange={jest.fn()}
      onSubmit={jest.fn()}
      isLoading={true}
    />);
    
    const input = screen.getByRole('textbox');
    expect(input).toBeDisabled();
  });
  
  test('handles Enter key press to submit form', () => {
    const mockOnSubmit = jest.fn();
    
    render(<ChatInput 
      value="test message" 
      onChange={jest.fn()}
      onSubmit={mockOnSubmit}
      isLoading={false}
    />);
    
    const input = screen.getByRole('textbox');
    fireEvent.keyDown(input, { key: 'Enter', code: 'Enter' });
    
    expect(mockOnSubmit).toHaveBeenCalled();
  });
  
  test('does not submit on Shift+Enter', () => {
    const mockOnSubmit = jest.fn();
    
    render(<ChatInput 
      value="test message" 
      onChange={jest.fn()}
      onSubmit={mockOnSubmit}
      isLoading={false}
    />);
    
    const input = screen.getByRole('textbox');
    fireEvent.keyDown(input, { key: 'Enter', code: 'Enter', shiftKey: true });
    
    expect(mockOnSubmit).not.toHaveBeenCalled();
  });
  
  test('does not submit when input is empty', () => {
    const mockOnSubmit = jest.fn();
    
    render(<ChatInput 
      value="" 
      onChange={jest.fn()}
      onSubmit={mockOnSubmit}
      isLoading={false}
    />);
    
    const form = screen.getByRole('form');
    fireEvent.submit(form);
    
    expect(mockOnSubmit).not.toHaveBeenCalled();
  });
  
  test('shows different button text when loading', () => {
    render(<ChatInput 
      value="test message" 
      onChange={jest.fn()}
      onSubmit={jest.fn()}
      isLoading={true}
    />);
    
    expect(screen.getByText('Sending...')).toBeInTheDocument();
  });
});