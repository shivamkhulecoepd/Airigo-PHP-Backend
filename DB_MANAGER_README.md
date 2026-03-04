# Airigo Job Portal Database Manager

This script provides comprehensive database management functionality for the Airigo Job Portal Backend.

## Features

- **Database Connectivity Check**: Verify connection to your database
- **Table Creation**: Create all required tables with proper schema
- **Table Verification**: Check which tables exist and which are missing
- **Database Health Check**: Complete database status overview
- **Sample Data**: Insert sample data for testing
- **Table Information**: View detailed structure of any table
- **Safe Table Dropping**: Drop all tables (with confirmation)

## Usage

Run the database manager from the command line:

```bash
php db_manager.php [command]
```

### Available Commands

#### Health Check (Default)
```bash
php db_manager.php health
```
Checks database connection, verifies all required tables exist, and provides health status.

#### Create Tables
```bash
php db_manager.php create-tables
```
Creates all required database tables with proper schema, indexes, and relationships.

#### Check Tables
```bash
php db_manager.php check-tables
```
Lists which tables exist and which are missing.

#### Insert Sample Data
```bash
php db_manager.php sample-data
```
Inserts sample data for testing purposes.

#### View Table Information
```bash
php db_manager.php table-info <table_name>
```
Shows detailed information about a specific table including columns, indexes, and record count.

Example:
```bash
php db_manager.php table-info users
```

#### Drop All Tables (Use with Caution!)
```bash
php db_manager.php drop-all
```
Drops all database tables. Requires confirmation before proceeding.

## Database Schema Files

This project includes multiple SQL schema files for different purposes:

### 1. database_schema.sql
- **Purpose**: Original schema from project requirements
- **Content**: Basic table definitions as specified in the project documentation
- **Use Case**: Reference for initial implementation

### 2. database_schema_updated.sql
- **Purpose**: Updated schema with all indexes and optimizations
- **Content**: Complete table definitions with all indexes and proper structure
- **Use Case**: Production deployment and database setup

### 3. database_schema_current.sql
- **Purpose**: Current live database structure
- **Content**: Exact schema of your current database as it exists
- **Use Case**: Documentation of current state, backup schema

## Required Tables

The system creates 6 essential tables:

1. **users**: Core user information (jobseekers and recruiters)
2. **jobseekers**: Jobseeker profile details
3. **recruiters**: Recruiter profile details and approval status
4. **jobs**: Job postings with approval workflow
5. **applications**: Job application tracking
6. **issues_reports**: User feedback and reporting system

## Database Schema

All tables are created with:
- Proper primary and foreign key relationships
- Essential indexes for performance
- Appropriate data types and constraints
- Timestamps for tracking creation and updates

## For Hostinger Deployment

The database manager works seamlessly with your Hostinger remote database configuration. Simply ensure your `.env` file contains the correct Hostinger database credentials:

```
DB_HOST=your_hostinger_ip
DB_PORT=3306
DB_NAME=your_database_name
DB_USER=your_username
DB_PASSWORD=your_password
```

## Troubleshooting

### Connection Issues
- Verify your database credentials in `.env` file
- Ensure your Hostinger database is accessible
- Check firewall settings if connecting remotely

### Missing Tables
- Run `php db_manager.php create-tables` to create missing tables
- Verify database permissions allow table creation

### Permission Issues
- Ensure the database user has CREATE, INSERT, UPDATE, DELETE permissions
- Contact your hosting provider if permissions are restricted

## Security Note

The `drop-all` command is destructive and irreversible. Always backup your data before using this command.