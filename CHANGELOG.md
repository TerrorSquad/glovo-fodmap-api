# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0](https://github.com/TerrorSquad/glovo-fodmap-api/compare/v0.2.0...v0.3.0) (2025-07-23)


### Features

* move from externalId to hashes ([4363c16](https://github.com/TerrorSquad/glovo-fodmap-api/commit/4363c16753c5a569d4373f9954c2a4a0805a5482))


### Bug Fixes

* update product classification API documentation to reflect changes in identifier usage ([f6c994f](https://github.com/TerrorSquad/glovo-fodmap-api/commit/f6c994fdb4aa32ce64ce9c90a1e2ac7b8d99da87))

## [0.2.0](https://github.com/TerrorSquad/glovo-fodmap-api/compare/v0.1.0...v0.2.0) (2025-07-19)


### Features

* add API key validation to Gemini service ([f2f4e83](https://github.com/TerrorSquad/glovo-fodmap-api/commit/f2f4e830e566ad711e61e5e20b2c658f95db6e8e))
* add API spec endpoints and health check ([65e151d](https://github.com/TerrorSquad/glovo-fodmap-api/commit/65e151d532c3313dd6561352602c2b57344289c0))
* add auto-startup configuration for Fly.io deployment ([986b066](https://github.com/TerrorSquad/glovo-fodmap-api/commit/986b066020faf8a0b1d61d8dc9000dd60a9674c6))
* add enhanced database schema with is_food, explanation, and processed_at fields ([5332581](https://github.com/TerrorSquad/glovo-fodmap-api/commit/533258179bf7d8b9107c500c19abd771965059ea))
* add Fodmap classification service and update classify method in ProductController ([ed800c2](https://github.com/TerrorSquad/glovo-fodmap-api/commit/ed800c26e3c48f9a16d83a7213d2ca25008efb70))
* add intelligent caching layer for FODMAP classification ([6c78b98](https://github.com/TerrorSquad/glovo-fodmap-api/commit/6c78b9855af0fb07473f29bf901f0662325c3ec9))
* add Larastan for static analysis and update phpstan configuration ([9698210](https://github.com/TerrorSquad/glovo-fodmap-api/commit/9698210130c3a0021c33abd6d96da0cada93966e))
* add NA classification for non-food products ([94606a4](https://github.com/TerrorSquad/glovo-fodmap-api/commit/94606a4ecdde1a713179ebdf1c13b878cbb5968c))
* add PHPStan configuration to VS Code settings ([9a884e2](https://github.com/TerrorSquad/glovo-fodmap-api/commit/9a884e2fc1cdd7658c86f6e93f2b1848a24bd6ec))
* add post-start script to update Nginx config for XDEBUG_TRIGGER ([af17efc](https://github.com/TerrorSquad/glovo-fodmap-api/commit/af17efc1c3dec9b4ee4162708188a8db1fb81c8e))
* add storage initialization script for setting up storage directory ([4de8013](https://github.com/TerrorSquad/glovo-fodmap-api/commit/4de80131588d6a3cdb1a95a771803a702a0b7813))
* clean API architecture with separated endpoints ([023bd56](https://github.com/TerrorSquad/glovo-fodmap-api/commit/023bd56ee8482aa7a8064cfbd22750b71015b463))
* deploy to fly.io ([a86dc13](https://github.com/TerrorSquad/glovo-fodmap-api/commit/a86dc130b2613f285a50c5db1307db9b3da32ed9))
* enable auto-shutdown for cost optimization ([e2b349f](https://github.com/TerrorSquad/glovo-fodmap-api/commit/e2b349fb842ed16ea4d762dd5371bfeb88585fae))
* enhance AI classification with Serbian explanations and optimized batch processing ([7dbda79](https://github.com/TerrorSquad/glovo-fodmap-api/commit/7dbda7918a6141fd1301cc30384ffabeae522ecb))
* enhance console commands with Serbian explanations and simplified flags ([8a4dc79](https://github.com/TerrorSquad/glovo-fodmap-api/commit/8a4dc792bf3057c34d729cfee5e64785283fdd22))
* implement background job architecture with Tier 1 optimizations ([a818abc](https://github.com/TerrorSquad/glovo-fodmap-api/commit/a818abca9d7c97ba9cb229f9a4fc6467a115383d))
* implement batch classification for AI classifier ([1d5c7b5](https://github.com/TerrorSquad/glovo-fodmap-api/commit/1d5c7b52f558af916903fabd2e0c757b1af5a561))
* implement custom exception handler for API responses ([7b689d4](https://github.com/TerrorSquad/glovo-fodmap-api/commit/7b689d42159d07491b49fc065c7ba93368579309))
* implement processed_at tracking to prevent infinite classification loops ([749c545](https://github.com/TerrorSquad/glovo-fodmap-api/commit/749c545b556e2100ad46b2362efc047b00cb2bcb))
* implement Product classification API with validation and migration ([c98ad6a](https://github.com/TerrorSquad/glovo-fodmap-api/commit/c98ad6aab69340acbaad0caabcd8f6494f0acece))
* integrate Google Gemini AI for FODMAP classification ([c616c5b](https://github.com/TerrorSquad/glovo-fodmap-api/commit/c616c5bb11b943dd59c193eb170c1aeba309beb6))
* turn off autostop ([1875135](https://github.com/TerrorSquad/glovo-fodmap-api/commit/187513522be6dbb2a17331a9dd0dc8f1e84010e8))
* update background jobs and API resources for enhanced classification data ([8eb7650](https://github.com/TerrorSquad/glovo-fodmap-api/commit/8eb7650e7afe2ea1022dea0fe7bfdd98d66830d7))
* update default batch size to 50 for better performance ([b559751](https://github.com/TerrorSquad/glovo-fodmap-api/commit/b559751cb5edcb6dc5cd62a97aa3fc26e42c7c7e))
* update project metadata with Serbian language and AI classification keywords ([feec057](https://github.com/TerrorSquad/glovo-fodmap-api/commit/feec05787cd59aad70a89a76f83544f2966b3252))


### Bug Fixes

* add queue worker to supervisor configuration ([5e4f4ba](https://github.com/TerrorSquad/glovo-fodmap-api/commit/5e4f4bade44b3c3cedcb03b2d5e6abd206cc9ca4))
* correct ProductStatusResource data structure ([57045cd](https://github.com/TerrorSquad/glovo-fodmap-api/commit/57045cd5a7dd1be99d75ad89845c59bb9f757373))
* guarantee batch classification for queue jobs ([c187e73](https://github.com/TerrorSquad/glovo-fodmap-api/commit/c187e73cf5208c6b29a43ca1242a013d1fca1760))
* improve Laravel IDE integration for VS Code extensions ([738f50f](https://github.com/TerrorSquad/glovo-fodmap-api/commit/738f50f3abde5e907fe8c3b026130edfd22d39ff))
* install dev dependencies for testing ([393ed3e](https://github.com/TerrorSquad/glovo-fodmap-api/commit/393ed3e5988e1c7bf765e04312c76fe4279cdfcd))
* remove redundant assertIsArray calls in tests ([36f94dc](https://github.com/TerrorSquad/glovo-fodmap-api/commit/36f94dc51c0011020cd2a6fa692e842769afbc9f))
* remove unnecessary SQLite service container ([d743012](https://github.com/TerrorSquad/glovo-fodmap-api/commit/d743012542354ccda1c6cc6043e9f08abf9b25b3))
* resolve Laravel IDE integration for VS Code extensions ([7e6c50b](https://github.com/TerrorSquad/glovo-fodmap-api/commit/7e6c50b84b88465ca016e8f29da8ed489daf3ab4))
* restore database connection settings in fly.toml ([30fd26f](https://github.com/TerrorSquad/glovo-fodmap-api/commit/30fd26f5af203de214b074e03e1d36fb04d5a290))
* runner now correctly runs shell commands inside ddev containers ([849855c](https://github.com/TerrorSquad/glovo-fodmap-api/commit/849855c6ab2017d75123d736332161c7273eda69))
* update auto_stop_machines and min_machines_running settings in fly.toml ([64b4e38](https://github.com/TerrorSquad/glovo-fodmap-api/commit/64b4e38acd0990120f287e92f9f8298a2c9a201b))
* update storage mount destination to correct path ([e0b22c0](https://github.com/TerrorSquad/glovo-fodmap-api/commit/e0b22c0e01d4aac755272202b6738c74ca300874))


### Performance Improvements

* add database indexes for improved query performance ([c5891ce](https://github.com/TerrorSquad/glovo-fodmap-api/commit/c5891ce2a81e186ae12423ac5dbbb5a9d2125231))
* classify only unclassified products ([6d96f5b](https://github.com/TerrorSquad/glovo-fodmap-api/commit/6d96f5b041890d34b1708ede184e605fd1a5f31e))

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
