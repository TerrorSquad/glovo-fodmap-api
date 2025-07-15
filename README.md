# Glovo FODMAP API

A Laravel-based REST API that classifies food products based on their FODMAP content. This API helps users with digestive sensitivities (IBS, SIBO) identify whether food products are high or low in FODMAPs.

## 🚀 Features

- **AI-Powered Classification**: Uses Google Gemini AI for intelligent FODMAP classification
- **Dual Classification System**: Choose between AI-powered or rule-based classification
- **Product Classification**: Automatically classifies products as HIGH, LOW, or UNKNOWN FODMAP content
- **Batch Processing**: Handle multiple products in a single API request
- **Smart Caching**: Avoids re-processing already classified products
- **RESTful API**: Clean and simple JSON API interface
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

This project supports both rule-based and AI-powered classification using Google Gemini.

### Enable Gemini AI Classification

1. **Get your Gemini API key** from [Google AI Studio](https://aistudio.google.com/app/apikey)

2. **Add to your `.env` file**:
   ```env
   GEMINI_API_KEY=your_api_key_here
   USE_GEMINI_CLASSIFIER=true
   ```

3. **Switch between classifiers**:
   - `USE_GEMINI_CLASSIFIER=true` - Uses AI-powered Gemini classification
   - `USE_GEMINI_CLASSIFIER=false` - Uses rule-based classification (default)

### Benefits of AI Classification

- **Intelligent Analysis**: Leverages Gemini's knowledge of food and FODMAP content
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
      "created_at": "2025-07-14T10:00:00Z",
      "updated_at": "2025-07-14T10:00:00Z"
    },
    {
      "external_id": "67890",
      "name": "Rice Cakes",
      "category": "Snacks",
      "status": "LOW",
      "created_at": "2025-07-14T10:00:00Z",
      "updated_at": "2025-07-14T10:00:00Z"
    }
  ]
}
```

### FODMAP Status Values

- **`HIGH`**: Contains high FODMAP ingredients
- **`LOW`**: Contains only low FODMAP ingredients
- **`NA`**: Non-food products (cosmetics, cleaning products, etc.)
- **`UNKNOWN`**: Cannot be classified (insufficient data)

## 🏗️ Architecture

### Key Components

- **`ProductController`**: Main API endpoint for product classification
- **`FodmapClassifierService`**: Core logic for FODMAP classification
- **`Product`**: Eloquent model for storing product data
- **`ProductResource`**: API resource for consistent JSON responses

### Classification Logic

The classifier uses configurable word lists to identify FODMAP content:

1. **High FODMAP ingredients**: Wheat, onions, garlic, apples, etc.
2. **Low FODMAP ingredients**: Rice, carrots, spinach, etc.
3. **Ignore list**: Brand names, measurements, common words

Configuration files are located in `config/fodmap.php`.

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
- `LOG_LEVEL`: Logging level (debug, info, warning, error)

### FODMAP Configuration

Customize FODMAP ingredients in `config/fodmap.php`:

- `fodmap.high`: High FODMAP ingredients
- `fodmap.low`: Low FODMAP ingredients
- `fodmap.ignore`: Words to ignore during classification

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
