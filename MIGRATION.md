# Migration Guide

This document explains the changes introduced in the latest version and how to migrate existing installations.

## Database Changes

### New Columns Added
The `products` table has been enhanced with three new nullable columns:

```sql
ALTER TABLE products ADD COLUMN is_food BOOLEAN NULL;
ALTER TABLE products ADD COLUMN explanation TEXT NULL;
ALTER TABLE products ADD COLUMN processed_at TIMESTAMP NULL;
```

### Migration
Run the migration to add new columns:
```bash
php artisan migrate
```

**Note**: All existing products will have these fields set to `null` initially. Run classification to populate them.

## API Response Changes

### Before
```json
{
  "external_id": "12345",
  "name": "Whole Wheat Bread",
  "category": "Bakery",
  "status": "HIGH",
  "created_at": "2025-07-14T10:00:00Z",
  "updated_at": "2025-07-14T10:00:00Z"
}
```

### After
```json
{
  "external_id": "12345",
  "name": "Whole Wheat Bread", 
  "category": "Bakery",
  "status": "HIGH",
  "is_food": true,
  "explanation": "Hleb od integralnog brašna sadrži gluten i fruktan iz pšenice...",
  "created_at": "2025-07-14T10:00:00Z",
  "updated_at": "2025-07-14T10:00:00Z",
  "processed_at": "2025-07-14T10:00:00Z"
}
```

## Console Command Changes

### Simplified Flags
The redundant `--external-id` flag has been removed from both commands. Use `--external-ids` for both single and multiple products:

```bash
# Before (deprecated)
php artisan fodmap:status --external-id=12345
php artisan fodmap:classify --external-id=12345

# After (recommended)  
php artisan fodmap:status --external-ids=12345
php artisan fodmap:classify --external-ids=12345

# Multiple products
php artisan fodmap:status --external-ids=12345,67890
php artisan fodmap:classify --external-ids=12345,67890
```

### New Options
- `--with-explanation`: Show detailed explanations in status command
- `--stats`: Show classification statistics  
- `--reprocess`: Include already processed products in classification

## Environment Variables

### Required for AI Classification
```env
GEMINI_API_KEY=your_api_key_here
GEMINI_MODEL=gemini-2.0-flash-exp
```

### Deprecated
The following are no longer used:
```env
USE_GEMINI_CLASSIFIER=true  # Deprecated - AI is now default
```

## Batch Processing

### Automatic Optimization
- Single products: Individual API calls
- Multiple products: Automatically uses batch processing
- Configurable batch size (default: 10, max: 100)
- Built-in rate limiting (2 seconds between batches)

### Configuration
```bash
# Custom batch size
php artisan fodmap:classify --all --batch-size=50

# Disable batch processing
php artisan fodmap:classify --all --no-batch
```

## Status Values

### New Status Values
- `MODERATE`: Products with moderate FODMAP levels
- `PENDING`: Products awaiting classification

### Existing Values
- `HIGH`: High FODMAP content
- `LOW`: Low FODMAP content  
- `UNKNOWN`: Classification failed
- `NA`: Non-food products

## Reset and Re-classification

### Reset All Products
To reset all products to PENDING status:
```bash
# Using Tinker
php artisan tinker --execute="Product::query()->update(['status' => 'PENDING', 'explanation' => null, 'processed_at' => null, 'is_food' => null]);"
```

### Re-classify Products
```bash
# Re-classify all products
php artisan fodmap:classify --all --force --reprocess

# Re-classify specific products
php artisan fodmap:classify --external-ids=12345,67890 --force
```

## Backward Compatibility

- All API responses are backward compatible (new fields are added, none removed)
- Existing console commands continue to work
- Database changes are additive (no data loss)
- Old status values remain valid

## Troubleshooting

### Common Issues

1. **Missing API Key**: Ensure `GEMINI_API_KEY` is set in `.env`
2. **Rate Limiting**: API includes built-in 2-second delays between batches
3. **Large Batches**: Batch size is limited to 100 for optimal performance
4. **Memory Issues**: Use smaller batch sizes for large datasets

### Debugging
```bash
# Verbose output
php artisan fodmap:classify --all --verbose

# Check logs
tail -f storage/logs/laravel.log

# Statistics
php artisan fodmap:status --stats
```

## Performance Improvements

- **10x API Efficiency**: Batch processing reduces API calls dramatically
- **Faster Classification**: Serbian explanations provide immediate context
- **Smart Caching**: Prevents re-processing of classified products
- **Optimized Queries**: Enhanced database queries for better performance
