#!/bin/bash

BASE_URL="http://localhost:8000"
TIMESTAMP=$(date +%s)
MOBILE_EMAIL="mobile_${TIMESTAMP}@example.com"
BUSINESS_EMAIL="business_${TIMESTAMP}@example.com"
PASSWORD="password123"
# COUNTRY_ID will be fetched dynamically

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "------------------------------------------------"
echo "API Test Script"
echo "Base URL: $BASE_URL"
echo "Timestamp: $TIMESTAMP"
echo "------------------------------------------------"

# Check for jq
if ! command -v jq &> /dev/null; then
    echo "jq is required but not installed. Please install it (brew install jq)."
    exit 1
fi

# Function to make a request
# Usage: make_request METHOD URL [DATA] [TOKEN]
function make_request {
    local method=$1
    local url=$2
    local data=$3
    local token=$4
    
    local headers=(-H "Accept: application/json" -H "Content-Type: application/json")
    if [ ! -z "$token" ]; then
        headers+=(-H "Authorization: Bearer $token")
    fi
    
    if [ ! -z "$data" ]; then
        response=$(curl -s -w "\n%{http_code}" -X "$method" "${headers[@]}" -d "$data" "$url")
    else
        response=$(curl -s -w "\n%{http_code}" -X "$method" "${headers[@]}" "$url")
    fi
    
    local body=$(echo "$response" | sed '$d')
    local status=$(echo "$response" | tail -n 1)
    
    if [ $status -ge 200 ] && [ $status -lt 300 ]; then
        echo -e "${GREEN}[$method] $url - $status${NC}"
        echo "$body" > response.json
        return 0
    else
        echo -e "${RED}[$method] $url - $status${NC}"
        echo "$body"
        return 1
    fi
}

echo -e "\n${YELLOW}--- Setup ---${NC}"
echo "Fetching Countries..."
make_request "GET" "$BASE_URL/api/v1/countries" "" ""
COUNTRY_ID=$(cat response.json | jq -r '.data[0].id // empty')
echo "Using Country ID: $COUNTRY_ID"

echo -e "\n${YELLOW}--- Mobile API Tests ---${NC}"

# 1. Register Mobile User
echo "Registering Mobile User..."
DATA="{\"name\":\"Mobile\",\"surname\":\"User\",\"email\":\"$MOBILE_EMAIL\",\"password\":\"$PASSWORD\",\"password_confirmation\":\"$PASSWORD\",\"country_id\":\"$COUNTRY_ID\",\"phone\":123456789,\"device_name\":\"TestScript\"}"
make_request "POST" "$BASE_URL/api/v1/users/register" "$DATA" ""
if [ $? -eq 0 ]; then
    MOBILE_TOKEN=$(cat response.json | jq -r '.data.token')
    echo "Mobile Token: $MOBILE_TOKEN"
else
    echo "Failed to register mobile user. Aborting mobile tests."
fi

# 2. Get Mobile Profile
if [ ! -z "$MOBILE_TOKEN" ]; then
    echo "Getting Mobile Profile..."
    make_request "GET" "$BASE_URL/api/v1/users/me" "" "$MOBILE_TOKEN"
fi

# 3. Logout Mobile User
if [ ! -z "$MOBILE_TOKEN" ]; then
    echo "Logging out Mobile User..."
    make_request "POST" "$BASE_URL/api/v1/users/logout" "" "$MOBILE_TOKEN"
fi

echo -e "\n${YELLOW}--- Business API Tests ---${NC}"

# 1. Register Business User
echo "Registering Business User..."
DATA="{\"name\":\"Business\",\"surname\":\"User\",\"email\":\"$BUSINESS_EMAIL\",\"password\":\"$PASSWORD\",\"password_confirmation\":\"$PASSWORD\",\"country_id\":\"$COUNTRY_ID\",\"phone\":\"987654321\",\"device_name\":\"TestScript\"}"
make_request "POST" "$BASE_URL/api/business/v1/business-users/register" "$DATA" ""
if [ $? -eq 0 ]; then
    BUSINESS_TOKEN=$(cat response.json | jq -r '.data.token')
    echo "Business Token: $BUSINESS_TOKEN"
else
    echo "Failed to register business user. Aborting business tests."
fi

# 2. Get Business Profile
if [ ! -z "$BUSINESS_TOKEN" ]; then
    echo "Getting Business Profile..."
    make_request "GET" "$BASE_URL/api/business/v1/business-users/me" "" "$BUSINESS_TOKEN"
fi

# 3. List Tenants
TENANT_ID=""
if [ ! -z "$BUSINESS_TOKEN" ]; then
    echo "Listing Tenants..."
    make_request "GET" "$BASE_URL/api/business/v1/business" "" "$BUSINESS_TOKEN"
    if [ $? -eq 0 ]; then
        TENANT_ID=$(cat response.json | jq -r '.data[0].id // empty')
        if [ ! -z "$TENANT_ID" ]; then
            echo "Found Tenant ID: $TENANT_ID"
        else
            echo "No tenants found for this business user."
        fi
    fi
fi

# 4. Tenant Scoped Tests (if tenant found)
if [ ! -z "$TENANT_ID" ] && [ ! -z "$BUSINESS_TOKEN" ]; then
    echo -e "\n${YELLOW}--- Tenant Scoped Tests ---${NC}"
    
    # Get Tenant Details
    echo "Getting Tenant Details..."
    make_request "GET" "$BASE_URL/api/business/v1/business/$TENANT_ID" "" "$BUSINESS_TOKEN"
    
    # List Court Types
    echo "Listing Court Types..."
    make_request "GET" "$BASE_URL/api/business/v1/business/$TENANT_ID/court-types" "" "$BUSINESS_TOKEN"
    COURT_TYPE_ID=$(cat response.json | jq -r '.data[0].id // empty')
    
    # Create Court Type if none exists
    if [ -z "$COURT_TYPE_ID" ]; then
        echo "Creating Court Type..."
        DATA="{\"name\":\"Test Court Type\",\"description\":\"Created by script\"}"
        make_request "POST" "$BASE_URL/api/business/v1/business/$TENANT_ID/court-types" "$DATA" "$BUSINESS_TOKEN"
        COURT_TYPE_ID=$(cat response.json | jq -r '.data.id // empty')
    fi
    
    if [ ! -z "$COURT_TYPE_ID" ]; then
        echo "Using Court Type ID: $COURT_TYPE_ID"
        
        # List Courts
        echo "Listing Courts..."
        make_request "GET" "$BASE_URL/api/business/v1/business/$TENANT_ID/courts" "" "$BUSINESS_TOKEN"
        COURT_ID=$(cat response.json | jq -r '.data[0].id // empty')
        
        # Create Court if none exists
        if [ -z "$COURT_ID" ]; then
            echo "Creating Court..."
            DATA="{\"name\":\"Test Court\",\"court_type_id\":\"$COURT_TYPE_ID\",\"price_per_hour\":1000,\"currency_id\":1,\"opening_time\":\"08:00\",\"closing_time\":\"22:00\"}"
            make_request "POST" "$BASE_URL/api/business/v1/business/$TENANT_ID/courts" "$DATA" "$BUSINESS_TOKEN"
            COURT_ID=$(cat response.json | jq -r '.data.id // empty')
        fi
        
        if [ ! -z "$COURT_ID" ]; then
            echo "Using Court ID: $COURT_ID"
            
            # Get Court Details
            echo "Getting Court Details..."
            make_request "GET" "$BASE_URL/api/business/v1/business/$TENANT_ID/courts/$COURT_ID" "" "$BUSINESS_TOKEN"
            
            # Create Availability
            echo "Creating Availability..."
            DATA="{\"day_of_week_recurring\":\"monday\",\"start_time\":\"10:00\",\"end_time\":\"11:00\",\"is_available\":true}"
            make_request "POST" "$BASE_URL/api/business/v1/business/$TENANT_ID/courts/$COURT_ID/availabilities" "$DATA" "$BUSINESS_TOKEN"
            
            # List Availabilities
            echo "Listing Availabilities..."
            make_request "GET" "$BASE_URL/api/business/v1/business/$TENANT_ID/courts/$COURT_ID/availabilities" "" "$BUSINESS_TOKEN"
            
            # Get Available Dates
            echo "Getting Available Dates..."
            START_DATE=$(date +%Y-%m-%d)
            END_DATE=$(date -v+7d +%Y-%m-%d 2>/dev/null || date -d "+7 days" +%Y-%m-%d)
            make_request "GET" "$BASE_URL/api/business/v1/business/$TENANT_ID/courts/$COURT_ID/availability/dates?start_date=$START_DATE&end_date=$END_DATE" "" "$BUSINESS_TOKEN"
            
            # Get Available Slots (using start date)
            echo "Getting Available Slots..."
            make_request "GET" "$BASE_URL/api/business/v1/business/$TENANT_ID/courts/$COURT_ID/availability/$START_DATE/slots" "" "$BUSINESS_TOKEN"
            
        else
            echo "Failed to get/create Court."
        fi
    else
        echo "Failed to get/create Court Type."
    fi
    
    # Financials
    echo "Getting Current Month Financials..."
    make_request "GET" "$BASE_URL/api/business/v1/business/$TENANT_ID/financials/current" "" "$BUSINESS_TOKEN"
    
    # Clients
    echo "Listing Clients..."
    make_request "GET" "$BASE_URL/api/business/v1/business/$TENANT_ID/clients" "" "$BUSINESS_TOKEN"
    
fi

# Cleanup
rm response.json
echo -e "\n${GREEN}Test Script Completed.${NC}"
