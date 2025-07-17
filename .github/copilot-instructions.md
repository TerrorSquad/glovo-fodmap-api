# GitHub Copilot Instructions

## Project Overview

This is a Laravel-based FODMAP classification API that uses Google Gemini AI to classify food products as LOW, MODERATE, or HIGH FODMAP. The system processes products from Glovo delivery platform and provides FODMAP information for users with digestive sensitivities.

## Architecture & Key Components

### Core Services
- **`GeminiFodmapClassifierService`**: Main AI classification service using Google Gemini API
- **`ClassifyProductsJob`**: Background queue job for batch processing
- **`Product` Model**: Database model for food products

### Technology Stack
- **Framework**: Laravel 11
- **Database**: SQLite (development), PostgreSQL (production)
- **Queue**: Database-backed queue system
- **AI Provider**: Google Gemini (`models/gemini-2.5-flash-lite-preview-06-17`)
- **Deployment**: Fly.io
- **Development**: DDEV for local environment

## Code Standards & Conventions

### PHP Standards
- Follow PSR-12 coding standards
- Use strict types: `declare(strict_types=1);`
- Prefer early returns over nested conditionals
- Use descriptive variable names and method names
- All classes must have proper docblocks

### Code Quality Tools
- **PHPStan**: Static analysis (level 8)
- **ECS (EasyCodingStandard)**: Code formatting
- **Rector**: Automated refactoring
- **Pre-commit hooks**: Automatically run all checks before commits

### Testing
- Write feature tests for API endpoints
- Write unit tests for services and complex logic
- Use factories for test data generation
- Test both success and failure scenarios

## AI Classification System

### Rate Limiting
- **Limit**: 60 API calls per minute (1 per second)
- **Batch Processing**: Preferred for queue jobs
- **Individual Processing**: Available for real-time requests
- **Rate Limit Handling**: Use `waitForRateLimit()` method to ensure API availability

### Batch vs Individual Classification
```php
// Queue jobs MUST use batch processing
$classifier->classifyBatch($products); // Always batch for queues

// Individual classification for real-time requests
$classifier->classify($product); // Single product processing
```

### Prompt Engineering
- Products are primarily in **Serbian/Bosnian/Croatian/Montenegrin**
- Include translation dictionary for common food terms
- Specify FODMAP categories clearly: LOW, MODERATE, HIGH
- Handle edge cases like alcohol, meat products, and processed foods

## Database Patterns

### Product Processing States
- `processed_at`: Timestamp when product was classified
- `status`: FODMAP classification (LOW/MODERATE/HIGH/UNKNOWN)
- Products should not remain UNKNOWN after processing

### Queue Job Coordination
- Jobs respect rate limits between executions
- Minimum 2-second intervals between queue jobs
- Use cache-based coordination for job timing

## Development Workflow

### Local Development
```bash
# Start DDEV environment
ddev start

# Run PHP commands through DDEV
ddev php artisan migrate
ddev php artisan queue:work

# Check syntax
ddev php -l app/Services/SomeService.php
```

### Git Workflow
- Pre-commit hooks run automatically
- All code must pass PHPStan, ECS, and Rector checks
- Use conventional commit messages (short and concise, one-line format)
- Test changes before committing

### Environment Configuration
- Local: SQLite database in `database/database.sqlite`
- Production: PostgreSQL on Fly.io
- API keys in `.env` file (Gemini API key required)

## Common Patterns

### Service Layer Pattern
```php
// Inject dependencies through constructor
class SomeService implements SomeInterface
{
    public function __construct(
        private readonly SomeRepository $repository,
        private readonly LoggerInterface $logger
    ) {}
}
```

### Error Handling
```php
try {
    // API call or business logic
} catch (\Exception $exception) {
    Log::error('Operation failed', [
        'context' => $context,
        'error' => $exception->getMessage(),
    ]);
    
    // Graceful fallback
    return $fallbackValue;
}
```

### Logging Standards
```php
Log::info('Operation completed', [
    'product_count' => count($products),
    'api_calls_used' => $this->getCurrentCallCount(),
    'duration' => $duration,
]);
```

## Deployment & Configuration

### Fly.io Configuration
- App: `glovo-fodmap-api`
- Database: PostgreSQL addon
- Environment variables configured via `fly.toml`

### Queue Processing
- Background jobs for bulk classification
- Failed jobs are retried with exponential backoff
- Monitor queue status via Laravel Horizon (if configured)

## Performance Considerations

### API Optimization
- Batch processing preferred (up to 100 products per batch)
- Rate limiting prevents quota exhaustion
- Caching for repeated classifications

### Database Optimization
- Index on `processed_at` and `status` columns
- Avoid N+1 queries in product relationships
- Use eager loading for related data

## Language-Specific Considerations

### Serbian Food Terms
When working with product names, be aware of common Serbian food terminology:
- "liker" = liqueur (alcoholic beverage)
- "rakija" = brandy/rakia
- "kulen" = kulen sausage
- "kobasica" = sausage
- "mesne prerađevine" = meat products

### FODMAP Classifications
- **LOW**: Safe for most people with IBS
- **MODERATE**: Small portions may be tolerated
- **HIGH**: Should be avoided or limited
- **UNKNOWN**: Classification failed or unavailable

## Troubleshooting

### Common Issues
1. **API Quota Exceeded**: Check rate limiting, ensure proper model name
2. **Products Stuck as UNKNOWN**: Verify prompt engineering and API responses
3. **Queue Jobs Failing**: Check database connections and error logs
4. **Pre-commit Hook Failures**: Run `ddev php vendor/bin/phpstan` to debug

### Debug Information
Always include relevant context in logs:
- Product names and categories
- API call counts and responses
- Error messages and stack traces
- Performance metrics

## Security

### API Keys
- Store in environment variables, never in code
- Use different keys for development/production
- Rotate keys regularly

### Input Validation
- Validate all product data before processing
- Sanitize user inputs in API endpoints
- Use Laravel's built-in validation rules

Remember: This project handles food classification for people with digestive health issues. Accuracy and reliability are critical for user safety and experience.
