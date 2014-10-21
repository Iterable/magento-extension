CODE_BASE_DIR=~/code/Iterable/magento
MAGENTO_INSTALL_DIR=~/Sites/magento
mkdir -p ${MAGENTO_INSTALL_DIR}/app/code/community/
mkdir -p ${MAGENTO_INSTALL_DIR}/skin/adminhtml/base/default/
mkdir -p ${MAGENTO_INSTALL_DIR}/app/design/adminhtml/default/default/layout/
ln -s ${CODE_BASE_DIR}/app/etc/modules/Iterable_TrackOrderPlaced.xml ${MAGENTO_INSTALL_DIR}/app/etc/modules/Iterable_TrackOrderPlaced.xml
ln -s ${CODE_BASE_DIR}/app/code/community/Iterable ${MAGENTO_INSTALL_DIR}/app/code/community/
ln -s ${CODE_BASE_DIR}/skin/adminhtml/base/default/iterable ${MAGENTO_INSTALL_DIR}/skin/adminhtml/base/default/
ln -s ${CODE_BASE_DIR}/app/design/adminhtml/default/default/layout/iterable ${MAGENTO_INSTALL_DIR}/app/design/adminhtml/default/default/layout/
ln -s ${CODE_BASE_DIR}/var/connect/Iterable_Plugin.xml ${MAGENTO_INSTALL_DIR}/var/connect/Iterable_Plugin.xml
