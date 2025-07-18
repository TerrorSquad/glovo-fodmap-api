# Glovo FODMAP API

A Laravel-based REST API that classifies food products based on their FODMAP content using Google Gemini AI. This API helps users with digestive sensitivities (IBS, SIBO) identify whether food products are high or low in FODMAPs.

## 🚀 Features

- **AI-Powered Classification**: Uses Google Gemini 2.5 Flash Lite for intelligent FODMAP classification
- **Serbian Language Support**: Provides explanations in Serbian for better user experience
- **Enhanced Classification**: Determines if products are food items and provides detailed explanations
- **Batch Processing**: Efficiently handles multiple products with optimized API usage (10x improvement)
- **Mixed Ingredient Handling**: Improved logic for products with both high and low FODMAP ingredients
- **Smart Caching**: Avoids re-processing already classified products
- **RESTful API**: Clean JSON API with rich product information
- **Console Commands**: Comprehensive CLI tools for classification and monitoring
- **OpenAPI Documentation**: Auto-generated API documentation
- **Multi-Environment Support**: Works with DDEV, Docker, or local development

## 📋 Requirements

- **PHP**: 8.2 or higher
- **Laravel**: 12.x
- **Database**: SQLite (default) or MySQL/PostgreSQL
- **Node.js**: 22.8.0 (managed via Volta)
- **Composer**: For PHP dependencies
- **PNPM**: For Node.js dependencies

## 🛠️ Installation

### Option 1: Using DDEV (Recommended)

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd glovo-fodmap-api
   ```

2. **Start DDEV environment**
   ```bash
   ddev start
   ddev composer install
   ddev pnpm install
   ```

3. **Set up the application**
   ```bash
   ddev artisan key:generate
   ddev artisan migrate
   ddev artisan db:seed
   ```

### Option 2: Local Development

1. **Clone and install dependencies**
   ```bash
   git clone <repository-url>
   cd glovo-fodmap-api
   composer install
   pnpm install
   ```

2. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database setup**
   ```bash
   touch database/database.sqlite
   php artisan migrate
   php artisan db:seed
   ```

4. **Start the development server**
   ```bash
   php artisan serve
   ```

## 🔧 Development Tools

This project includes a smart runner script that automatically detects your environment:

```bash
# The runner script works in both DDEV and local environments
./tools/runner.sh artisan migrate
./tools/runner.sh composer install
./tools/runner.sh pnpm dev
```

## 🤖 AI Classification Setup

This project uses Google Gemini 2.5 Flash Lite for advanced FODMAP classification with Serbian explanations.

### Enable Gemini AI Classification

1. **Get your Gemini API key** from [Google AI Studio](https://aistudio.google.com/app/apikey)

2. **Add to your `.env` file**:
   ```env
   GEMINI_API_KEY=your_api_key_here
   GEMINI_MODEL=gemini-2.0-flash-exp  # Default model
   ```

### Benefits of AI Classification

- **Intelligent Analysis**: Leverages Gemini's knowledge of food and FODMAP content
- **Serbian Explanations**: Provides detailed explanations in Serbian language
- **Food Detection**: Automatically determines if product is food or non-food
- **Mixed Ingredient Logic**: Handles products with conflicting FODMAP ingredients
- **Batch Optimization**: Processes multiple products in single API calls (10x efficiency)
- **Better Accuracy**: Handles complex and uncommon food products
- **Contextual Understanding**: Considers product names and categories together
- **Fallback Safety**: Falls back to "unknown" classification if API fails

## 📖 API Usage

### Classify Products

**Endpoint**: `POST /api/v1/classify`

**Request Body**:
```json
{
  "products": [
    {
      "externalId": "12345",
      "name": "Whole Wheat Bread",
      "category": "Bakery"
    },
    {
      "externalId": "67890",
      "name": "Rice Cakes",
      "category": "Snacks"
    }
  ]
}
```

**Response**:
```json
{
  "results": [
    {
      "external_id": "12345",
      "name": "Whole Wheat Bread",
      "category": "Bakery",
      "status": "HIGH",
      "is_food": true,
      "explanation": "Hleb od integralnog brašna sadrži gluten i fruktan iz pšenice, što ga čini visoko FODMAP namirnicom.",
      "created_at": "2025-07-14T10:00:00Z",
      "updated_at": "2025-07-14T10:00:00Z",
      "processed_at": "2025-07-14T10:00:00Z"
    },
    {
      "external_id": "67890",
      "name": "Rice Cakes",
      "category": "Snacks",
      "status": "LOW",
      "is_food": true,
      "explanation": "Pirinčane galette su napravljene od pirinča koji je nisko FODMAP namirnica i siguran za potrošnju.",
      "created_at": "2025-07-14T10:00:00Z",
      "updated_at": "2025-07-14T10:00:00Z",
      "processed_at": "2025-07-14T10:00:00Z"
    }
  ]
}
### FODMAP Status Values

- **`HIGH`**: Contains high FODMAP ingredients
- **`LOW`**: Contains only low FODMAP ingredients  
- **`MODERATE`**: Contains moderate levels of FODMAP ingredients
- **`NA`**: Non-food products (cosmetics, cleaning products, etc.)
- **`UNKNOWN`**: Cannot be classified (insufficient data or mixed ingredients)
- **`PENDING`**: Awaiting classification

### Response Fields

- **`status`**: FODMAP classification status
- **`is_food`**: Boolean indicating if product is food (true/false/null)
- **`explanation`**: Detailed explanation in Serbian language
- **`processed_at`**: Timestamp when product was classified

## 🖥️ Console Commands

The API includes powerful console commands for classification and monitoring:

### Classify Products

```bash
# Classify specific products
php artisan fodmap:classify --external-ids=12345,67890

# Classify all unclassified products
php artisan fodmap:classify --all

# Re-classify all products (force reprocessing)
php artisan fodmap:classify --all --force --reprocess

# Use batch processing with custom batch size
php artisan fodmap:classify --all --batch-size=50

# Disable batch processing (slower but more reliable)
php artisan fodmap:classify --all --no-batch
```

### View Product Status

```bash
# Show recent products in table format
php artisan fodmap:status --limit=10

# Show specific products with detailed explanations
php artisan fodmap:status --external-ids=12345,67890 --with-explanation

# Filter by status
php artisan fodmap:status --status=HIGH --limit=5

# Show classification statistics
php artisan fodmap:status --stats
```

### Command Options

**Classify Options:**
- `--external-ids`: Specific products to classify (comma-separated)
- `--all`: Process all products in database
- `--force`: Re-classify products with existing status
- `--reprocess`: Include already processed products
- `--batch-size`: Products per batch (default: 10, max: 100)
- `--no-batch`: Disable batch processing

**Status Options:**
- `--external-ids`: Show specific products (comma-separated)
- `--status`: Filter by status (HIGH/LOW/MODERATE/UNKNOWN/PENDING/NA)
- `--limit`: Number of products to show (default: 10)
- `--with-explanation`: Show detailed view with explanations
- `--stats`: Show classification statistics

## 🏗️ Architecture

### Key Components

- **`ProductController`**: Main API endpoint for product classification
- **`GeminiFodmapClassifierService`**: AI-powered classification using Google Gemini
- **`ClassifyProductsJob`**: Background job for processing product batches
- **`Product`**: Eloquent model with enhanced fields (is_food, explanation, processed_at)
- **`ProductResource`**: API resource for consistent JSON responses with all fields
- **Console Commands**: `ClassifyProducts` and `ShowProductStatus` for CLI management

### Enhanced Classification Logic

The AI classifier provides:

1. **Serbian Language Explanations**: Detailed reasoning in Serbian
2. **Food Detection**: Automatically identifies food vs non-food products
3. **Mixed Ingredient Handling**: Smart logic for products with conflicting FODMAP levels
4. **Batch Processing**: Optimized API calls for efficiency
5. **Structured Responses**: Consistent JSON format with all required fields

### Database Schema

Enhanced `products` table includes:
- `status`: FODMAP classification (HIGH/LOW/MODERATE/UNKNOWN/NA/PENDING)
- `is_food`: Boolean field indicating if product is food (nullable)
- `explanation`: Detailed explanation in Serbian (nullable text)
- `processed_at`: Timestamp of classification (nullable)

## 🧪 Testing

Run the test suite:

```bash
# Using runner script (works in any environment)
./tools/runner.sh php artisan test

# Or directly with PHPUnit
./tools/runner.sh vendor/bin/phpunit
```

## 📊 Code Quality

This project includes comprehensive code quality tools:

- **PHPStan**: Static analysis (`./tools/runner.sh vendor/bin/phpstan analyse`)
- **Psalm**: Additional static analysis
- **Laravel Pint**: Code formatting (`./tools/runner.sh vendor/bin/pint`)
- **Rector**: Code modernization
- **ECS**: Easy Coding Standard

Run all quality checks:
```bash
./tools/runner.sh composer check
```

## 📚 API Documentation

Generate OpenAPI documentation:

```bash
./tools/runner.sh php documentation/api.php
pnpm run generate:api-doc:html
```

This creates:
- `documentation/openapi.yml` - OpenAPI specification
- `documentation/openapi.html` - Human-readable documentation

## 🚀 Deployment

### Using Fly.io

This project is configured for deployment on Fly.io:

```bash
fly deploy
```

The `Dockerfile` and `fly.toml` are pre-configured for production deployment.

### Auto-Startup Configuration

For production environments with auto-shutdown/startup (like Fly.io), the application automatically:

1. **Initializes on startup** via `.fly/scripts/3_fodmap_startup.sh`:
   - Waits for database connection
   - Runs migrations
   - Optimizes Laravel caches

2. **Sets up automatic scheduling**:
   - Laravel scheduler runs as daemon via `.fly/supervisor/conf.d/scheduler.conf`
   - Automatically restarts if the process crashes
   - Processes products every 2 minutes with overlap protection
   - Logs to `/var/log/laravel-scheduler.log`

3. **Health monitoring**:
   - `/api/health` endpoint includes scheduler status
   - Monitors pending products and recent processing activity
   - Alerts if scheduler may need attention

### Deployment Features

- **Zero-downtime startup**: Database waits and gradual initialization
- **Automatic queue processing**: Products are classified every 2 minutes without manual intervention
- **Overlap protection**: Only one classification job runs at a time
- **Self-healing**: Supervisor automatically restarts failed processes
- **Health monitoring**: Built-in endpoints to verify scheduler functionality

### Manual Deployment

1. **Build assets**
   ```bash
   pnpm run build
   ```

2. **Optimize for production**
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

## 🔧 Configuration

### Environment Variables

Key environment variables:

- `APP_ENV`: Application environment (local, staging, production)
- `DB_CONNECTION`: Database driver (sqlite, mysql, pgsql)
- `DB_DATABASE`: Database name or path
- `GEMINI_API_KEY`: Google Gemini API key for AI classification
- `GEMINI_MODEL`: Gemini model to use (default: gemini-2.0-flash-exp)
- `LOG_LEVEL`: Logging level (debug, info, warning, error)

### Configuration Files

- `config/gemini.php`: Gemini AI service configuration
- `config/fodmap.php`: Legacy FODMAP word lists (deprecated)
- `config/app.php`: Core Laravel application settings

## 🤝 Contributing

1. **Fork the repository**
2. **Create a feature branch** (`git checkout -b feature/amazing-feature`)
3. **Commit your changes** (`git commit -m 'Add amazing feature'`)
4. **Push to the branch** (`git push origin feature/amazing-feature`)
5. **Open a Pull Request**

### Code Standards

- Follow PSR-12 coding standards
- Use strict typing (`declare(strict_types=1)`)
- Write tests for new features
- Update documentation as needed

## 📝 License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## 🆘 Support

For support and questions:

1. Check the [API documentation](documentation/openapi.html)
2. Review existing [issues](../../issues)
3. Create a new issue with detailed information

## 🙏 Acknowledgments

- Built with [Laravel Framework](https://laravel.com)
- FODMAP data based on [Monash University FODMAP research](https://www.monashfodmap.com)
- Development environment powered by [DDEV](https://ddev.readthedocs.io)

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
