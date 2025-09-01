# ============================================================================
# TechyPark Engine Ultimate - Multi-Cloud Terraform Configuration
# Supports AWS, GCP, and DigitalOcean
# ============================================================================

terraform {
  required_version = ">= 1.6.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    google = {
      source  = "hashicorp/google"
      version = "~> 5.0"
    }
    digitalocean = {
      source  = "digitalocean/digitalocean"
      version = "~> 2.34"
    }
    kubernetes = {
      source  = "hashicorp/kubernetes"
      version = "~> 2.24"
    }
    helm = {
      source  = "hashicorp/helm"
      version = "~> 2.12"
    }
  }
}

# ============================================================================
# Local Variables
# ============================================================================

locals {
  # Common tags/labels for all resources
  common_tags = {
    Project     = "techypark-engine"
    Environment = var.environment
    Platform    = var.cloud_platform
    ManagedBy   = "terraform"
    Owner       = "techypark-team"
    Version     = "2.0.0"
  }

  # Resource naming convention
  name_prefix = "techypark-${var.environment}"

  # Multi-cloud configuration
  cloud_config = {
    aws = {
      kubernetes_version = "1.28"
      database_engine_version = "15.4"
      redis_engine_version = "7.0"
      instance_types = {
        small  = "t3.medium"
        medium = "t3.large"
        large  = "t3.xlarge"
      }
    }
    gcp = {
      kubernetes_version = "1.28"
      database_version = "POSTGRES_15"
      redis_version = "REDIS_7_0"
      machine_types = {
        small  = "e2-medium"
        medium = "e2-standard-2"
        large  = "e2-standard-4"
      }
    }
    digitalocean = {
      kubernetes_version = "1.28.2-do.0"
      database_version = "15"
      redis_version = "7"
      droplet_sizes = {
        small  = "s-2vcpu-2gb"
        medium = "s-2vcpu-4gb"
        large  = "s-4vcpu-8gb"
      }
    }
  }
}

# ============================================================================
# Data Sources
# ============================================================================

# Get current cloud provider information
data "aws_caller_identity" "current" {
  count = var.cloud_platform == "aws" ? 1 : 0
}

data "google_client_config" "current" {
  count = var.cloud_platform == "gcp" ? 1 : 0
}

# ============================================================================
# AWS Infrastructure
# ============================================================================

module "aws_infrastructure" {
  count  = var.cloud_platform == "aws" ? 1 : 0
  source = "./modules/aws"

  # Basic configuration
  name_prefix = local.name_prefix
  environment = var.environment
  region      = var.region

  # Kubernetes configuration
  kubernetes_version = local.cloud_config.aws.kubernetes_version
  node_instance_type = local.cloud_config.aws.instance_types[var.instance_size]
  min_nodes         = var.min_nodes
  max_nodes         = var.max_nodes

  # Database configuration
  database_instance_class = "db.${local.cloud_config.aws.instance_types[var.instance_size]}"
  database_engine_version = local.cloud_config.aws.database_engine_version
  database_backup_retention = var.backup_retention_days

  # Redis configuration
  redis_node_type       = "cache.${local.cloud_config.aws.instance_types[var.instance_size]}"
  redis_engine_version  = local.cloud_config.aws.redis_engine_version
  redis_num_cache_nodes = var.redis_nodes

  # Storage configuration
  s3_bucket_name = "${local.name_prefix}-storage-${random_string.suffix.result}"

  # Networking
  enable_vpc_logs = var.environment == "production"

  # Monitoring
  enable_enhanced_monitoring = var.enable_monitoring

  # Security
  enable_encryption = true

  # Tags
  tags = local.common_tags
}

# ============================================================================
# GCP Infrastructure
# ============================================================================

module "gcp_infrastructure" {
  count  = var.cloud_platform == "gcp" ? 1 : 0
  source = "./modules/gcp"

  # Basic configuration
  project_id  = var.gcp_project_id
  name_prefix = local.name_prefix
  environment = var.environment
  region      = var.region

  # Kubernetes configuration
  kubernetes_version = local.cloud_config.gcp.kubernetes_version
  machine_type      = local.cloud_config.gcp.machine_types[var.instance_size]
  min_nodes        = var.min_nodes
  max_nodes        = var.max_nodes

  # Database configuration
  database_tier           = "db-custom-${var.instance_size == "small" ? "1-3840" : var.instance_size == "medium" ? "2-7680" : "4-15360"}"
  database_version        = local.cloud_config.gcp.database_version
  database_backup_enabled = true

  # Redis configuration
  redis_memory_size_gb = var.instance_size == "small" ? 1 : var.instance_size == "medium" ? 2 : 4
  redis_version       = local.cloud_config.gcp.redis_version
  redis_tier         = var.environment == "production" ? "STANDARD_HA" : "BASIC"

  # Storage configuration
  bucket_name     = "${local.name_prefix}-storage-${random_string.suffix.result}"
  storage_class   = var.environment == "production" ? "STANDARD" : "REGIONAL"

  # Networking
  enable_private_google_access = true

  # Monitoring
  enable_monitoring = var.enable_monitoring

  # Security
  enable_workload_identity = true
  enable_network_policy   = true

  # Labels
  labels = local.common_tags
}

# ============================================================================
# DigitalOcean Infrastructure  
# ============================================================================

module "digitalocean_infrastructure" {
  count  = var.cloud_platform == "digitalocean" ? 1 : 0
  source = "./modules/digitalocean"

  # Basic configuration
  name_prefix = local.name_prefix
  environment = var.environment
  region      = var.region

  # Kubernetes configuration
  kubernetes_version = local.cloud_config.digitalocean.kubernetes_version
  node_size         = local.cloud_config.digitalocean.droplet_sizes[var.instance_size]
  min_nodes        = var.min_nodes
  max_nodes        = var.max_nodes

  # Database configuration
  database_size     = "db-${local.cloud_config.digitalocean.droplet_sizes[var.instance_size]}"
  database_version  = local.cloud_config.digitalocean.database_version
  database_num_nodes = var.environment == "production" ? 2 : 1

  # Redis configuration
  redis_size    = "db-s-1vcpu-1gb"
  redis_version = local.cloud_config.digitalocean.redis_version

  # Storage configuration
  spaces_name   = "${local.name_prefix}-storage"
  enable_cdn    = true

  # Networking
  enable_firewalls = true

  # Monitoring
  enable_monitoring = var.enable_monitoring

  # Tags
  tags = [for k, v in local.common_tags : "${k}:${v}"]
}

# ============================================================================
# Shared Resources
# ============================================================================

# Random suffix for unique resource names
resource "random_string" "suffix" {
  length  = 8
  special = false
  upper   = false
}

# ============================================================================
# Kubernetes Provider Configuration
# ============================================================================

# Configure Kubernetes provider based on cloud platform
data "aws_eks_cluster" "cluster" {
  count = var.cloud_platform == "aws" ? 1 : 0
  name  = module.aws_infrastructure[0].cluster_name
}

data "aws_eks_cluster_auth" "cluster" {
  count = var.cloud_platform == "aws" ? 1 : 0
  name  = module.aws_infrastructure[0].cluster_name
}

data "google_container_cluster" "cluster" {
  count    = var.cloud_platform == "gcp" ? 1 : 0
  name     = module.gcp_infrastructure[0].cluster_name
  location = var.region
  project  = var.gcp_project_id
}

provider "kubernetes" {
  # AWS EKS configuration
  dynamic "exec" {
    for_each = var.cloud_platform == "aws" ? [1] : []
    content {
      api_version = "client.authentication.k8s.io/v1beta1"
      command     = "aws"
      args        = ["eks", "get-token", "--cluster-name", data.aws_eks_cluster.cluster[0].name]
    }
  }

  # GCP GKE configuration
  host  = var.cloud_platform == "gcp" ? "https://${data.google_container_cluster.cluster[0].endpoint}" : null
  token = var.cloud_platform == "gcp" ? data.google_client_config.current[0].access_token : null
  cluster_ca_certificate = var.cloud_platform == "gcp" ? base64decode(data.google_container_cluster.cluster[0].master_auth[0].cluster_ca_certificate) : null

  # DigitalOcean DOKS configuration
  host  = var.cloud_platform == "digitalocean" ? module.digitalocean_infrastructure[0].cluster_endpoint : null
  token = var.cloud_platform == "digitalocean" ? module.digitalocean_infrastructure[0].cluster_token : null
  cluster_ca_certificate = var.cloud_platform == "digitalocean" ? base64decode(module.digitalocean_infrastructure[0].cluster_ca_certificate) : null
}

# ============================================================================
# Application Deployment
# ============================================================================

# Kubernetes namespace
resource "kubernetes_namespace" "techypark" {
  metadata {
    name = "techypark"

    labels = {
      name = "techypark"
      environment = var.environment
      platform = var.cloud_platform
    }
  }
}

# ConfigMap for application configuration
resource "kubernetes_config_map" "app_config" {
  metadata {
    name      = "techypark-config"
    namespace = kubernetes_namespace.techypark.metadata[0].name
  }

  data = {
    CLOUD_PLATFORM = var.cloud_platform
    ENVIRONMENT    = var.environment
    REGION         = var.region

    # Database configuration (platform-specific)
    DATABASE_HOST = var.cloud_platform == "aws" ? module.aws_infrastructure[0].database_endpoint : (
      var.cloud_platform == "gcp" ? module.gcp_infrastructure[0].database_private_ip : 
      module.digitalocean_infrastructure[0].database_private_host
    )
    DATABASE_NAME = "techypark_engine"

    # Redis configuration (platform-specific)  
    REDIS_HOST = var.cloud_platform == "aws" ? module.aws_infrastructure[0].redis_primary_endpoint : (
      var.cloud_platform == "gcp" ? module.gcp_infrastructure[0].redis_host :
      module.digitalocean_infrastructure[0].redis_private_host
    )

    # Storage configuration (platform-specific)
    STORAGE_BUCKET = var.cloud_platform == "aws" ? module.aws_infrastructure[0].s3_bucket_name : (
      var.cloud_platform == "gcp" ? module.gcp_infrastructure[0].bucket_name :
      module.digitalocean_infrastructure[0].spaces_name
    )

    # AI Configuration
    ENABLE_AI_COPILOT     = "true"
    ENABLE_VISUAL_BUILDER = "true" 
    ENABLE_ANALYTICS      = "true"

    # Feature flags
    ENABLE_MONITORING = tostring(var.enable_monitoring)
    ENABLE_BACKUPS   = "true"
  }
}

# Secrets for sensitive data
resource "kubernetes_secret" "app_secrets" {
  metadata {
    name      = "techypark-secrets"
    namespace = kubernetes_namespace.techypark.metadata[0].name
  }

  data = {
    # Database credentials (platform-specific)
    DATABASE_PASSWORD = var.cloud_platform == "aws" ? module.aws_infrastructure[0].database_password : (
      var.cloud_platform == "gcp" ? module.gcp_infrastructure[0].database_password :
      module.digitalocean_infrastructure[0].database_password
    )

    # Application secrets
    JWT_SECRET     = random_password.jwt_secret.result
    SESSION_SECRET = random_password.session_secret.result

    # AI API keys (to be set manually or via CI/CD)
    OPENAI_API_KEY    = ""
    ANTHROPIC_API_KEY = ""
  }

  type = "Opaque"
}

# Generate random secrets
resource "random_password" "jwt_secret" {
  length  = 32
  special = true
}

resource "random_password" "session_secret" {
  length  = 32
  special = true
}
