#!/bin/bash

URL="https://smehurko.com/admins"


URL_PATH=$(curl -L -s -k -c cookies.txt \
     -X POST \
     -F 'ajax=1' \
     -F 'token=' \
     -F 'controller=AdminLogin' \
     -F 'submitLogin=1' \
     -F 'passwd=!SladoleD88!' \
     -F 'email=admin@smehurko.com' \
     -F 'redirect=AdminSyncSuppliers' \
     ${URL}/ajax-tab.php?rand=1638588882348 \
     | jq -r '.redirect'
     
)

SYNC_URL="${URL}/${URL_PATH}"

#echo ${SYNC_URL}

curl -s -k -L -b cookies.txt \
     -X POST \
     -F 'feed_url="F:\\prj\\smehurko.com\\prestashop_modules\\syncsuppliers\\testData\\dataExport_partial.xml"' \
     -F 'syncMoni=1' \
     "${SYNC_URL}" 1> /dev/null

