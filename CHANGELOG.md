# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Serbian Language Support**: AI explanations now provided in Serbian language
- **Enhanced Database Schema**: Added `is_food`, `explanation`, and `processed_at` columns to products table
- **Food Detection**: AI automatically determines if products are food or non-food items
- **Batch Processing Optimization**: 10x API efficiency improvement with optimized batch processing
- **Mixed Ingredient Logic**: Improved classification for products with both high and low FODMAP ingredients
- **Console Commands**: Complete CLI toolset with `fodmap:classify` and `fodmap:status` commands
- **Detailed Statistics**: Enhanced statistics showing food classification and explanation coverage

### Enhanced
- **GeminiFodmapClassifierService**: Complete refactor for structured JSON responses
- **ProductResource**: Enhanced API responses with all new fields (is_food, explanation, processed_at)
- **Batch Processing**: Fixed JSON parsing issues for Gemini's markdown-wrapped responses
- **Console Commands**: Rich formatting, progress bars, and comprehensive options
- **Error Handling**: Improved error handling for batch processing failures

### Changed
- **API Responses**: Now include `is_food`, `explanation`, and `processed_at` fields
- **Classification Logic**: AI-driven classification replaces rule-based system
- **Status Values**: Added `MODERATE` and `PENDING` statuses for better classification granularity
- **Console Interface**: Simplified command flags (removed redundant --external-id option)
- **Documentation**: Updated README with all new features and usage examples

### Fixed
- **Statistics Bug**: Fixed duplicate counting in Pending/Unclassified products
- **JSON Parsing**: Resolved issues with Gemini's markdown code block responses
- **Batch Processing**: Improved reliability and error handling for large batches
- **Memory Management**: Optimized for processing large datasets

### Technical Improvements
- **Database Migration**: Added nullable columns for backward compatibility
- **API Efficiency**: Reduced API calls from 1-per-product to 1-per-batch (10-100 products)
- **Response Time**: Significantly improved classification speed with batch processing
- **Code Quality**: Enhanced error handling, logging, and debugging capabilities
- **OpenAPI Documentation**: Updated with all new response fields and examples

### Performance
- **10x API Efficiency**: Batch processing reduces API calls dramatically
- **Optimized Prompts**: Improved AI prompts for better accuracy and consistency
- **Rate Limiting**: Built-in rate limiting respect for Gemini API
- **Caching**: Smart caching prevents re-processing of classified products

## [1.0.0] - 2025-07-18

### Added
- Initial release with basic FODMAP classification
- Google Gemini AI integration
- REST API for product classification
- Console commands for product management
- OpenAPI documentation
- DDEV development environment
- Comprehensive test suite
