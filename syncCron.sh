#!/bin/bash

if [[ -z ${SMEHURKO_USER} || -z ${SMEHURKO_PASS} ]]; then
  echo "Missing credentials SMEHURKO_USER and SMEHURKO_PASS"
  exit 1
fi

URL="https://smehurko.com/admins"


URL_PATH=$(curl -L -s -k -c cookies.txt \
     -X POST \
     -F 'ajax=1' \
     -F 'token=' \
     -F 'controller=AdminLogin' \
     -F 'submitLogin=1' \
     -F "passwd=${SMEHURKO_PASS}" \
     -F "email=${SMEHURKO_USER}" \
     -F 'redirect=AdminSyncSuppliers' \
     ${URL}/ajax-tab.php?rand=1618680841014 \
     | jq -r '.redirect'
     
)

SYNC_URL="${URL}/${URL_PATH}"

LOG_LVL=0

################################
echo "Sync Moni Bg"
DATA_FEED="http://195.162.72.127:83/MW/MoniTradeExport2.aspx"
#DATA_FEED="http://varnanet.info:8000/full_moni_2021-06-01.xml"
#DATA_FEED="file:///C:/Users/ivaylo.ivanov/OneDrive/Projects/sites/smehurko/testData/full_moni_2021-06-01.xml"
SUPPLIER_ID=2
curl -s -k -L -b cookies.txt -X POST -F "log_lvl=${LOG_LVL}" -F "feed_url=${DATA_FEED}" -F "supplier=${SUPPLIER_ID}" "${SYNC_URL}" > /dev/null

#################################
echo "Sync Mouse Toys"
DATA_FEED="https://mousetoys.eu/module.php?ModuleName=com.seliton.superxmlexport&Username=beborani&Domain=beborani.bg&Signature=1605de16641711281b52f2f40b8d9303c02c3a58"
#DATA_FEED="file:///C:/Users/ivaylo.ivanov/OneDrive/Projects/sites/smehurko/testData/partial_mouseToys.xml"
SUPPLIER_ID=4
curl -s -k -L -b cookies.txt -X POST -F "log_lvl=${LOG_LVL}" -F "feed_url=${DATA_FEED}" -F "supplier=${SUPPLIER_ID}" "${SYNC_URL}" > /dev/null

#################################
echo "Sync Bright Toys"
DATA_FEED="https://bright-toys.com/Products_stock_price.xml"
#DATA_FEED="file:///C:/Users/ivaylo.ivanov/OneDrive/Projects/sites/smehurko/testData/full_brightToys_2021-06-01.xml"
SUPPLIER_ID=5
curl -s -k -L -b cookies.txt -X POST -F "log_lvl=${LOG_LVL}" -F "feed_url=${DATA_FEED}" -F "supplier=${SUPPLIER_ID}" "${SYNC_URL}" > /dev/null


rm -f cookies.txt
     
#cat ${LOG_FILE}
