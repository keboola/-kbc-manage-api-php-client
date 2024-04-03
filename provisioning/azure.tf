provider "azurerm" {
  features {}
  tenant_id       = var.azure_tenant_id
  subscription_id = var.azure_subscription_id
}
provider "azuread" {
  tenant_id = var.azure_tenant_id
}

variable "azure_storage_location" {
  type = string
}
variable "azure_tenant_id" {
  type    = string
}
variable "azure_subscription_id" {
  type    = string
}

data "azurerm_client_config" "current" {}
data "azuread_client_config" "current" {}

locals {
  resource_group_uuid = substr(md5(azurerm_resource_group.mapi_resource_group.id), 0, 17)
}

resource "azurerm_resource_group" "mapi_resource_group" {
  name     = "${var.name_prefix}-mapi_resource_group"
  location = var.azure_storage_location
}

module "create_storage" {
  source = "./modules/azure/storage"

  resource_group_location = azurerm_resource_group.mapi_resource_group.location
  resource_group_uuid = local.resource_group_uuid
  resource_group_name = azurerm_resource_group.mapi_resource_group.name
  files_container = "dummy"
}

output "TEST_ABS_ACCOUNT_NAME" {
  value = module.create_storage.storage_account_files_name
}
output "TEST_ABS_ACCOUNT_KEY" {
  value     = module.create_storage.storage_account_primary_access_key
  sensitive = true
}
output "TEST_ABS_CONTAINER_NAME" {
  value = module.create_storage.storage_container_files_test_container_name
}
output "TEST_ABS_REGION" {
  value = module.create_storage.storage_account_location
}
output "TEST_ABS_ROTATE_ACCOUNT_KEY" {
  value     = module.create_storage.storage_account_secondary_access_key
  sensitive = true
}
