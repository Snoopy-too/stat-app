#!/bin/bash
fuser -k 4001/tcp 2>/dev/null || pkill -f "4001"
echo "Server stopped on port 4001"

