# Troubleshooting Guide

## BCP Import Fails
- Check BCP URL format is correct
- Verify BCP event ID is valid
- Check network connectivity to BCP API
- Review exponential backoff retry logs

## Database Connection Issues
- Verify MySQL is running: `docker-compose ps`
- Check credentials in `config/database.php`
- Ensure database exists
- Check port 3306 is not already in use

## E2E Tests Fail
- Ensure test environment is running: `docker-compose -f docker-compose.yml -f docker-compose.test.yml ps`
- Run migrations for test DB: `docker-compose -f docker-compose.yml -f docker-compose.test.yml run --rm migrate`
- Check Playwright browser installation: `npx playwright install`
- Review test artifacts in `playwright-report/`

## Permission Issues (Docker)
- Files created by Docker are owned by root
- Fix with: `sudo chown -R $USER:$USER .`
