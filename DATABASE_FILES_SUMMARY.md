# Airigo Job Portal - Database Files Summary

This document provides an overview of all database-related files in the Airigo Job Portal Backend.

## File Inventory

| File | Type | Purpose | Last Modified |
|------|------|---------|---------------|
| `db_manager.php` | PHP Script | Database management utility | Current |
| `database_schema.sql` | SQL Schema | Original project schema | Updated |
| `database_schema_updated.sql` | SQL Schema | Enhanced schema with indexes | New |
| `database_schema_current.sql` | SQL Schema | Live database structure export | Generated |

## Detailed Descriptions

### 1. db_manager.php
- **Type**: PHP Executable Script
- **Purpose**: Comprehensive database management tool
- **Features**:
  - Database connectivity testing
  - Table creation and verification
  - Health checks
  - Sample data insertion
  - Table structure inspection
  - Safe table dropping (with confirmation)

### 2. database_schema.sql
- **Type**: SQL Schema Definition
- **Purpose**: Original schema from project requirements
- **Content**: Core table definitions with basic structure
- **Use Cases**:
  - Initial project setup reference
  - Schema comparison
  - Backup schema definition

### 3. database_schema_updated.sql
- **Type**: SQL Schema Definition
- **Purpose**: Enhanced schema with all optimizations
- **Content**: Complete table definitions with:
  - All indexes for performance
  - Proper foreign key relationships
  - Appropriate constraints
  - Optimized column definitions
- **Use Cases**:
  - Production deployment
  - Database recreation
  - Performance optimization reference

### 4. database_schema_current.sql
- **Type**: SQL Schema Export
- **Purpose**: Current live database structure
- **Content**: Exact schema as exists in your Hostinger database
- **Use Cases**:
  - Documentation of current state
  - Migration reference
  - Backup schema for recovery
  - Audit purposes

## Usage Recommendations

### For New Deployments
1. Use `database_schema_updated.sql` for fresh installations
2. Or run `php db_manager.php create-tables` for automatic creation

### For Schema Updates
1. Modify the schema in `db_manager.php` as needed
2. Regenerate using the schema generation functionality
3. Update `database_schema_updated.sql` accordingly

### For Backup/Recovery
1. Use `database_schema_current.sql` to document current structure
2. Keep regular exports when making changes

## Maintenance

### Regular Tasks
- **Health Check**: Run `php db_manager.php health` periodically
- **Schema Verification**: Compare current schema with definitions
- **Backup**: Export current schema regularly

### When Making Changes
1. Update the schema definitions in `db_manager.php`
2. Test with the database manager
3. Update `database_schema_updated.sql`
4. Document changes in this summary

## Hostinger Compatibility

All database files are compatible with Hostinger's MySQL/MariaDB setup. Ensure your `.env` file has the correct Hostinger database credentials:

```
DB_HOST=193.203.184.189
DB_PORT=3306
DB_NAME=u233781988_airigoDB
DB_USER=u233781988_airigoDB
DB_PASSWORD='Airigo@#2026'
```

## Security Considerations

- Store database credentials securely in `.env` file
- Don't commit `.env` to version control
- Use strong passwords for database accounts
- Limit database user permissions to necessary operations only

## Support

For database-related issues:
1. Check connection with `php db_manager.php health`
2. Verify schema with `php db_manager.php check-tables`
3. Consult the error logs in the `logs/` directory
4. Refer to the Hostinger database management documentation