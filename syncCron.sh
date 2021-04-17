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
LOG_LVL=0


#DATA_FEED="http://195.162.72.127:83/MW/MoniTradeExport2.aspx"
#SUPPLIER_ID=2
#LOG_FILE="/mnt/f/prj/smehurko.com/public_html/log/product_sync_moni.log"

#DATA_FEED="https://mousetoys.eu/module.php?ModuleName=com.seliton.superxmlexport&Username=beborani&Domain=beborani.bg&Signature=1605de16641711281b52f2f40b8d9303c02c3a58"
#SUPPLIER_ID=4
#LOG_FILE="/mnt/f/prj/smehurko.com/public_html/log/product_sync_mouseToys.log"

DATA_FEED="https://bright-toys.com/Products_stock_price.xml"
#DATA_FEED="file://F:/prj/smehurko.com/prestashop_modules/syncsuppliers/testData/full_brightToys.xml"
SUPPLIER_ID=5
LOG_FILE="/mnt/f/prj/smehurko.com/public_html/log/product_sync_brightToys.log"

curl -s -k -L -b cookies.txt -X POST -F "log_lvl=${LOG_LVL}" -F "feed_url=${DATA_FEED}" -F "supplier=${SUPPLIER_ID}" "${SYNC_URL}" > /dev/null

rm -f cookies.txt
     
cat ${LOG_FILE}
