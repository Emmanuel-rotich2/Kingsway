# How to Run the API Test Suite

## Prerequisites

1. **PHP Server Running**
   - Ensure your PHP/Apache server is running
   - Default URL: `http://localhost:8000`
   - Update `BASE_URL` in `test_schedules_schoolconfig_api.sh` if different

2. **MySQL Database**
   - Database must be running and configured
   - Database name: `KingsWayAcademy` (as per config)
   - Tables must exist (run migrations if needed)

3. **Bash & Curl**
   ```bash
   # Verify curl is installed
   curl --version
   
   # Verify bash
   bash --version
   ```

---

## Quick Start

### Step 1: Navigate to Project Directory
```bash
cd /home/prof_angera/Projects/php_pages/Kingsway
```

### Step 2: Make Script Executable (if not already)
```bash
chmod +x test_schedules_schoolconfig_api.sh
```

### Step 3: Run the Test Suite
```bash
./test_schedules_schoolconfig_api.sh
```

### Step 4: Review Results
```bash
# View the log file (real-time)
tail -f api_test_*.log

# View the JSON results
cat api_test_results_*.json | jq .

# Or without jq (plain output)
cat api_test_results_*.json
```

---

## Output Files

After running the tests, two files are generated:

1. **api_test_TIMESTAMP.log**
   - Detailed log of all test execution
   - Includes HTTP status codes
   - Shows request/response details
   - Color-coded pass/fail indicators

2. **api_test_results_TIMESTAMP.json**
   - All responses in JSON format
   - Can be parsed for further analysis
   - Useful for automated testing pipelines

Example:
```bash
api_test_1734684900.log
api_test_results_1734684900.json
```

---

## Understanding Test Output

### Successful Test
```
[2024-12-20 09:30:15] Test #1: GET Schedules Index
[2024-12-20 09:30:15]   Method: GET | Endpoint: schedules/index
[2024-12-20 09:30:15]   HTTP Status: 200
✓ PASSED: GET Schedules Index (HTTP 200)
```

### Failed Test
```
[2024-12-20 09:30:16] Test #2: POST Create Schedule
[2024-12-20 09:30:16]   Method: POST | Endpoint: schedules
[2024-12-20 09:30:16]   HTTP Status: 400
✗ FAILED: POST Create Schedule (Expected: 201, Got: 400)
```

### Summary
```
Tests Passed: 45
Tests Failed: 2
Total Tests: 47
Pass Percentage: 96%
```

---

## Custom Configuration

### Change Base URL
Edit the script and modify:
```bash
BASE_URL="http://your-server:port"
```

### Run Specific Tests
You can comment out sections in the script:
```bash
# Comment out sections you don't want to test
# For example, comment out SECTION 10 for workflow tests
```

### Add Additional Tests
Add new test cases in any section:
```bash
test_endpoint "METHOD" "endpoint" "payload" "Test Name" "Expected_HTTP_Code"
```

---

## Troubleshooting

### Script Not Executable
```bash
chmod +x test_schedules_schoolconfig_api.sh
```

### Connection Refused
- Check if server is running
- Verify BASE_URL is correct
- Check firewall settings

### Invalid JSON Response
- Check if API endpoints are correctly implemented
- Review server error logs
- Verify database connection

### Tests Failing
- Check database has test data
- Verify foreign key relationships
- Review API controller implementation
- Check database schema matches expectations

---

## Integration with CI/CD

### Using with Jenkins/GitLab CI

```bash
#!/bin/bash
# CI/CD Pipeline Script

cd /path/to/Kingsway
./test_schedules_schoolconfig_api.sh

# Check result
if [ $? -eq 0 ]; then
    echo "All API tests passed!"
    exit 0
else
    echo "Some API tests failed!"
    exit 1
fi
```

### Parse Results Programmatically
```bash
# Extract pass rate
PASS_RATE=$(grep "Pass Percentage" api_test_*.log | grep -o '[0-9]*%')
echo "Pass Rate: $PASS_RATE"

# Check for failures
FAILURES=$(grep "FAILED" api_test_*.log | wc -l)
if [ $FAILURES -gt 0 ]; then
    echo "Found $FAILURES failing tests"
fi
```

---

## Advanced Usage

### Test Specific Endpoint Type
Create a custom script that runs only certain tests:

```bash
#!/bin/bash
# test_timetable_only.sh

BASE_URL="http://localhost:8000"
TIMESTAMP=$(date +%s)
LOG_FILE="timetable_test_${TIMESTAMP}.log"

# Only run timetable tests
test_endpoint "GET" "schedules/timetable-get" "" "GET Timetable" "200"
test_endpoint "POST" "schedules/timetable-create" "..." "POST Timetable" "201"
```

### Performance Testing
Add timing information:

```bash
#!/bin/bash
START=$(date +%s%N)
# ... make request ...
END=$(date +%s%N)
DURATION=$((($END - $START) / 1000000))
echo "Response time: ${DURATION}ms"
```

---

## Expected Results

### Typical Test Run
```
========================================
TEST SUMMARY
========================================
Tests Passed: 45
Tests Failed: 2
Total Tests: 47
Pass Percentage: 96%

Test Reports:
  - Log File: api_test_1734684900.log
  - Results File: api_test_results_1734684900.json
```

### Success Indicators
- ✓ Most tests passing (>90%)
- ✓ No database connection errors
- ✓ Proper HTTP status codes returned
- ✓ Valid JSON in responses
- ✓ Consistent response format

### Items to Investigate if Tests Fail
- Missing test data in database
- Incorrect foreign key relationships
- Invalid validation rules
- Database schema mismatches
- API implementation issues

---

## Clean Up

### Remove Old Test Files
```bash
# Remove logs older than 7 days
find . -name "api_test_*.log" -mtime +7 -delete
find . -name "api_test_results_*.json" -mtime +7 -delete
```

---

## Next Steps After Testing

1. **If All Tests Pass** ✓
   - API is working correctly
   - Ready for production deployment
   - Can proceed with frontend integration

2. **If Tests Fail**
   - Review SCHEDULES_API_TESTING_GUIDE.md
   - Check database schema
   - Debug failing endpoint in controller
   - Re-run tests to verify fix

3. **For Frontend Integration**
   - Reference SCHEDULES_API_QUICK_REFERENCE.md
   - Use payload examples from guide
   - Test endpoints manually with curl first
   - Implement error handling

---

## Support & Documentation

- **Full Documentation**: See `SCHEDULES_API_TESTING_GUIDE.md`
- **Quick Reference**: See `SCHEDULES_API_QUICK_REFERENCE.md`
- **Implementation Summary**: See `API_TESTING_SUMMARY.md`

---

**Last Updated**: December 20, 2024
**Version**: 1.0
