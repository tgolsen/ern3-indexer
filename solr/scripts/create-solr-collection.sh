#!/bin/bash

SOLR_HOST=${SOLR_HOST:-http://localhost:8983/solr}
COLLECTION_NAME=${SOLR_COLLECTION:-ern3}

echo "Checking if Solr collection '$COLLECTION_NAME' exists..."
CURRENT_COLLECTIONS=$(curl -s "$SOLR_HOST/admin/collections?action=LIST" | grep -oP '(?<="collections":\[)[^]]*' | tr -d '"' | tr ',' '\n')

if echo "$CURRENT_COLLECTIONS" | grep -q "$COLLECTION_NAME"; then
    echo "Collection '$COLLECTION_NAME' already exists. Skipping creation."
else
    echo "Creating Solr collection '$COLLECTION_NAME'..."
    CREATE_RESPONSE=$(curl -s "$SOLR_HOST/admin/collections" \
        --data-urlencode "action=CREATE" \
        --data-urlencode "name=$COLLECTION_NAME" \
        --data-urlencode "numShards=1" \
        --data-urlencode "replicationFactor=1" \
        --data-urlencode "collection.configName=_default")

    echo "$CREATE_RESPONSE" | grep -q "success" && \
        echo "Collection '$COLLECTION_NAME' created successfully." || \
        echo "Error creating collection: $CREATE_RESPONSE"
fi
