#!/bin/bash

# ============================================================================
# TechyPark Engine Ultimate - Universal Multi-Cloud Deployment
# Supports AWS, Google Cloud Platform, and DigitalOcean
# ============================================================================

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
LOG_FILE="/tmp/techypark-deploy.log"

# Default values
CLOUD_PLATFORM="${CLOUD_PLATFORM:-}"
ENVIRONMENT="${ENVIRONMENT:-production}"
REGION="${REGION:-}"
SKIP_CONFIRMATION="${SKIP_CONFIRMATION:-false}"
DRY_RUN="${DRY_RUN:-false}"

# ============================================================================
# Utility Functions
# ============================================================================

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a "$LOG_FILE"
}

log_info() {
    echo -e "${BLUE}[INFO] $1${NC}" | tee -a "$LOG_FILE"
}

log_warn() {
    echo -e "${YELLOW}[WARN] $1${NC}" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[ERROR] $1${NC}" | tee -a "$LOG_FILE"
}

log_success() {
    echo -e "${GREEN}[SUCCESS] $1${NC}" | tee -a "$LOG_FILE"
}

show_banner() {
    clear
    echo -e "${PURPLE}"
    cat << "EOF"
╔══════════════════════════════════════════════════════════════════════╗
║                                                                      ║
║  ████████╗███████╗ ██████╗██╗  ██╗██╗   ██╗██████╗  █████╗ ██████╗  ║
║  ╚══██╔══╝██╔════╝██╔════╝██║  ██║╚██╗ ██╔╝██╔══██╗██╔══██╗██╔══██╗ ║
║     ██║   █████╗  ██║     ███████║ ╚████╔╝ ██████╔╝███████║██████╔╝ ║
║     ██║   ██╔══╝  ██║     ██╔══██║  ╚██╔╝  ██╔═══╝ ██╔══██║██╔══██╗ ║
║     ██║   ███████╗╚██████╗██║  ██║   ██║   ██║     ██║  ██║██║  ██║ ║
║     ╚═╝   ╚══════╝ ╚═════╝╚═╝  ╚═╝   ╚═╝   ╚═╝     ╚═╝  ╚═╝╚═╝  ╚═╝ ║
║                                                                      ║
║                    🚀 ULTIMATE MULTI-CLOUD EDITION 🌍               ║
║                                                                      ║
║          AWS  •  Google Cloud  •  DigitalOcean  •  Universal        ║
║                                                                      ║
╚══════════════════════════════════════════════════════════════════════╝
EOF
    echo -e "${NC}"
    echo -e "${CYAN}AI-Powered WordPress Hosting Platform${NC}"
    echo -e "${CYAN}Version: 2.0.0 Ultimate Edition${NC}"
    echo -e "${CYAN}Author: TechyPark Team${NC}"
    echo ""
}

# ============================================================================
# Platform Detection
# ============================================================================

detect_platform() {
    log_info "Detecting cloud platform..."

    if [[ -n "$CLOUD_PLATFORM" ]]; then
        log "Platform specified: $CLOUD_PLATFORM"
        return 0
    fi

    # Check for AWS credentials
    if command -v aws &> /dev/null && aws sts get-caller-identity &> /dev/null; then
        CLOUD_PLATFORM="aws"
        log "Detected AWS environment"
        return 0
    fi

    # Check for GCP credentials
    if command -v gcloud &> /dev/null && gcloud auth list --filter=status:ACTIVE --format="value(account)" | grep -q "@"; then
        CLOUD_PLATFORM="gcp"
        log "Detected Google Cloud Platform"
        return 0
    fi

    # Check for DigitalOcean credentials
    if [[ -n "${DIGITALOCEAN_TOKEN:-}" ]] && command -v doctl &> /dev/null; then
        CLOUD_PLATFORM="digitalocean"
        log "Detected DigitalOcean environment"
        return 0
    fi

    log_warn "Could not auto-detect cloud platform"
    return 1
}

select_platform() {
    if [[ -n "$CLOUD_PLATFORM" ]]; then
        return 0
    fi

    echo -e "${YELLOW}Please select your cloud platform:${NC}"
    echo ""
    echo "1) Amazon Web Services (AWS)"
    echo "   ✓ Global scale (25+ regions)"
    echo "   ✓ Advanced AI/ML services"
    echo "   ✓ Enterprise features"
    echo "   ✓ Spot instances for cost optimization"
    echo ""
    echo "2) Google Cloud Platform (GCP)"
    echo "   ✓ Best-in-class AI/ML"
    echo "   ✓ BigQuery analytics"
    echo "   ✓ Sustainable infrastructure"
    echo "   ✓ Committed use discounts"
    echo ""
    echo "3) DigitalOcean"
    echo "   ✓ Simple, predictable pricing"
    echo "   ✓ Developer-friendly"
    echo "   ✓ Fast deployment"
    echo "   ✓ Excellent documentation"
    echo ""

    while true; do
        read -p "Enter your choice (1, 2, or 3): " choice
        case $choice in
            1)
                CLOUD_PLATFORM="aws"
                log_info "Selected Amazon Web Services"
                break
                ;;
            2)
                CLOUD_PLATFORM="gcp"
                log_info "Selected Google Cloud Platform"
                break
                ;;
            3)
                CLOUD_PLATFORM="digitalocean"
                log_info "Selected DigitalOcean"
                break
                ;;
            *)
                echo "Please enter 1, 2, or 3"
                ;;
        esac
    done
}

# ============================================================================
# Prerequisites Check
# ============================================================================

check_prerequisites() {
    log_info "Checking prerequisites..."

    local missing_tools=()

    # Common tools
    local common_tools=("docker" "kubectl" "terraform" "node" "npm")
    for tool in "${common_tools[@]}"; do
        if ! command -v "$tool" &> /dev/null; then
            missing_tools+=("$tool")
        fi
    done

    # Platform-specific tools
    case "$CLOUD_PLATFORM" in
        aws)
            if ! command -v "aws" &> /dev/null; then
                missing_tools+=("aws-cli")
            fi
            if ! command -v "eksctl" &> /dev/null; then
                missing_tools+=("eksctl")
            fi
            ;;
        gcp)
            if ! command -v "gcloud" &> /dev/null; then
                missing_tools+=("gcloud")
            fi
            ;;
        digitalocean)
            if ! command -v "doctl" &> /dev/null; then
                missing_tools+=("doctl")
            fi
            ;;
    esac

    if [[ ${#missing_tools[@]} -gt 0 ]]; then
        log_error "Missing required tools: ${missing_tools[*]}"
        echo ""
        echo "Installation guides:"
        echo "• Docker: https://docs.docker.com/get-docker/"
        echo "• kubectl: https://kubernetes.io/docs/tasks/tools/"
        echo "• Terraform: https://learn.hashicorp.com/tutorials/terraform/install-cli"
        echo "• Node.js: https://nodejs.org/"

        case "$CLOUD_PLATFORM" in
            aws)
                echo "• AWS CLI: https://docs.aws.amazon.com/cli/latest/userguide/install-cliv2.html"
                echo "• eksctl: https://eksctl.io/introduction/#installation"
                ;;
            gcp)
                echo "• gcloud CLI: https://cloud.google.com/sdk/docs/install"
                ;;
            digitalocean)
                echo "• doctl: https://docs.digitalocean.com/reference/doctl/how-to/install/"
                ;;
        esac

        exit 1
    fi

    log_success "All prerequisites are installed"
}

# ============================================================================
# Configuration
# ============================================================================

setup_configuration() {
    log_info "Setting up configuration for $CLOUD_PLATFORM..."

    # Set default region if not specified
    if [[ -z "$REGION" ]]; then
        case "$CLOUD_PLATFORM" in
            aws)
                REGION="us-east-1"
                ;;
            gcp)
                REGION="us-central1"
                ;;
            digitalocean)
                REGION="nyc3"
                ;;
        esac
        log "Using default region: $REGION"
    fi

    # Create environment-specific configuration
    local config_file="$PROJECT_ROOT/.env.$ENVIRONMENT"

    cat > "$config_file" << EOF
# TechyPark Engine - $CLOUD_PLATFORM $ENVIRONMENT Configuration
# Generated: $(date)

# Platform Configuration
CLOUD_PLATFORM=$CLOUD_PLATFORM
ENVIRONMENT=$ENVIRONMENT
REGION=$REGION

# Application
APP_NAME="TechyPark Engine Ultimate"
APP_VERSION="2.0.0"
NODE_ENV=$ENVIRONMENT

# Platform-specific settings
$(case "$CLOUD_PLATFORM" in
    aws)
        echo "# AWS Configuration"
        echo "AWS_REGION=$REGION"
        echo "EKS_CLUSTER_NAME=techypark-$ENVIRONMENT-eks"
        echo "RDS_INSTANCE_CLASS=db.t3.medium"
        echo "ELASTICACHE_NODE_TYPE=cache.t3.micro"
        echo "S3_BUCKET_NAME=techypark-$ENVIRONMENT-storage"
        ;;
    gcp)
        echo "# GCP Configuration"
        echo "GCP_PROJECT_ID=\${PROJECT_ID}"
        echo "GCP_REGION=$REGION"
        echo "GKE_CLUSTER_NAME=techypark-$ENVIRONMENT-gke"
        echo "CLOUDSQL_INSTANCE_NAME=techypark-$ENVIRONMENT-db"
        echo "MEMORYSTORE_INSTANCE_NAME=techypark-$ENVIRONMENT-redis"
        echo "GCS_BUCKET_NAME=\${PROJECT_ID}-$ENVIRONMENT-storage"
        ;;
    digitalocean)
        echo "# DigitalOcean Configuration"
        echo "DO_REGION=$REGION"
        echo "DOKS_CLUSTER_NAME=techypark-$ENVIRONMENT-k8s"
        echo "DO_DATABASE_NAME=techypark-$ENVIRONMENT-db"
        echo "DO_REDIS_NAME=techypark-$ENVIRONMENT-redis"
        echo "DO_SPACES_NAME=techypark-$ENVIRONMENT-spaces"
        ;;
esac)

# Database
DATABASE_URL=
REDIS_URL=

# Security
JWT_SECRET=\$(openssl rand -base64 32)
SESSION_SECRET=\$(openssl rand -base64 32)
ENCRYPTION_KEY=\$(openssl rand -base64 32)

# Features
ENABLE_AI_COPILOT=true
ENABLE_VISUAL_BUILDER=true
ENABLE_ANALYTICS=true
ENABLE_MONITORING=true
ENABLE_BACKUPS=true

# AI Configuration
OPENAI_API_KEY=
ANTHROPIC_API_KEY=

# Email
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASS=

EOF

    log_success "Configuration created: $config_file"
}

# ============================================================================
# Infrastructure Deployment
# ============================================================================

deploy_infrastructure() {
    log "Deploying infrastructure on $CLOUD_PLATFORM..."

    local terraform_dir="$PROJECT_ROOT/infrastructure/terraform/environments/$CLOUD_PLATFORM/$ENVIRONMENT"

    if [[ ! -d "$terraform_dir" ]]; then
        log_error "Terraform configuration not found: $terraform_dir"
        exit 1
    fi

    cd "$terraform_dir"

    # Initialize Terraform
    log_info "Initializing Terraform..."
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "DRY RUN: Would run terraform init"
    else
        terraform init
    fi

    # Plan infrastructure
    log_info "Planning infrastructure changes..."
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "DRY RUN: Would run terraform plan"
    else
        terraform plan -out=tfplan
    fi

    # Apply infrastructure
    log_info "Applying infrastructure..."
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "DRY RUN: Would run terraform apply"
    else
        terraform apply tfplan
    fi

    cd "$PROJECT_ROOT"
    log_success "Infrastructure deployed successfully"
}

# ============================================================================
# Application Deployment
# ============================================================================

build_application() {
    log "Building TechyPark Engine application..."

    cd "$PROJECT_ROOT"

    # Install dependencies
    log_info "Installing dependencies..."
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "DRY RUN: Would run npm install"
    else
        npm install
    fi

    # Build application
    log_info "Building application..."
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "DRY RUN: Would run npm run build"
    else
        npm run build
    fi

    # Build Docker images
    log_info "Building Docker images..."
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "DRY RUN: Would run docker build commands"
    else
        npm run docker:build
    fi

    log_success "Application built successfully"
}

deploy_to_kubernetes() {
    log "Deploying to Kubernetes cluster..."

    local k8s_overlay="$PROJECT_ROOT/kubernetes/overlays/$CLOUD_PLATFORM/$ENVIRONMENT"

    if [[ ! -d "$k8s_overlay" ]]; then
        log_error "Kubernetes overlay not found: $k8s_overlay"
        exit 1
    fi

    # Update kubeconfig
    case "$CLOUD_PLATFORM" in
        aws)
            log_info "Updating kubeconfig for EKS..."
            if [[ "$DRY_RUN" == "true" ]]; then
                log_info "DRY RUN: Would update kubeconfig for EKS"
            else
                aws eks update-kubeconfig --region "$REGION" --name "techypark-$ENVIRONMENT-eks"
            fi
            ;;
        gcp)
            log_info "Updating kubeconfig for GKE..."
            if [[ "$DRY_RUN" == "true" ]]; then
                log_info "DRY RUN: Would update kubeconfig for GKE"
            else
                gcloud container clusters get-credentials "techypark-$ENVIRONMENT-gke" --region "$REGION"
            fi
            ;;
        digitalocean)
            log_info "Updating kubeconfig for DOKS..."
            if [[ "$DRY_RUN" == "true" ]]; then
                log_info "DRY RUN: Would update kubeconfig for DOKS"
            else
                doctl kubernetes cluster kubeconfig save "techypark-$ENVIRONMENT-k8s"
            fi
            ;;
    esac

    # Deploy to Kubernetes
    log_info "Applying Kubernetes manifests..."
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "DRY RUN: Would apply Kubernetes manifests"
    else
        kubectl apply -k "$k8s_overlay"
    fi

    # Wait for rollout
    log_info "Waiting for deployment to complete..."
    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "DRY RUN: Would wait for deployment rollout"
    else
        kubectl rollout status deployment/techypark-backend -n techypark --timeout=600s
        kubectl rollout status deployment/techypark-frontend -n techypark --timeout=600s
    fi

    log_success "Application deployed to Kubernetes"
}

# ============================================================================
# Post-Deployment Tasks
# ============================================================================

setup_monitoring() {
    log "Setting up monitoring and observability..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "DRY RUN: Would setup monitoring stack"
        return 0
    fi

    # Install Prometheus and Grafana
    kubectl apply -f "$PROJECT_ROOT/monitoring/prometheus/"
    kubectl apply -f "$PROJECT_ROOT/monitoring/grafana/"

    log_success "Monitoring setup completed"
}

verify_deployment() {
    log "Verifying deployment..."

    if [[ "$DRY_RUN" == "true" ]]; then
        log_info "DRY RUN: Would verify deployment"
        return 0
    fi

    # Check pod status
    log_info "Checking pod status..."
    kubectl get pods -n techypark

    # Health checks
    log_info "Running health checks..."
    local attempts=0
    local max_attempts=30

    while [[ $attempts -lt $max_attempts ]]; do
        if kubectl get pods -n techypark | grep -q "Running"; then
            log_success "Pods are running successfully"
            break
        fi

        ((attempts++))
        log_info "Waiting for pods to be ready... ($attempts/$max_attempts)"
        sleep 10
    done

    if [[ $attempts -eq $max_attempts ]]; then
        log_error "Deployment verification failed"
        return 1
    fi

    log_success "Deployment verified successfully"
}

show_access_info() {
    log "Deployment completed successfully!"
    echo ""
    echo -e "${CYAN}==================== ACCESS INFORMATION ====================${NC}"
    echo ""

    # Get load balancer IP
    local lb_ip
    case "$CLOUD_PLATFORM" in
        aws|gcp)
            lb_ip=$(kubectl get service techypark-frontend-lb -n techypark -o jsonpath='{.status.loadBalancer.ingress[0].ip}' 2>/dev/null || echo "pending")
            ;;
        digitalocean)
            lb_ip=$(kubectl get service techypark-frontend-lb -n techypark -o jsonpath='{.status.loadBalancer.ingress[0].ip}' 2>/dev/null || echo "pending")
            ;;
    esac

    echo -e "${BLUE}Platform:${NC}           $CLOUD_PLATFORM"
    echo -e "${BLUE}Environment:${NC}        $ENVIRONMENT"
    echo -e "${BLUE}Region:${NC}            $REGION"
    echo -e "${BLUE}Load Balancer IP:${NC}   $lb_ip"
    echo ""

    if [[ "$lb_ip" != "pending" ]]; then
        echo -e "${BLUE}Application URL:${NC}     http://$lb_ip"
        echo -e "${BLUE}Dashboard:${NC}          http://$lb_ip/dashboard"
        echo -e "${BLUE}API:${NC}               http://$lb_ip/api"
        echo -e "${BLUE}AI Co-Pilot:${NC}       http://$lb_ip/ai-copilot"
    else
        echo -e "${YELLOW}Load balancer IP is still being assigned. Please wait a few minutes.${NC}"
    fi

    echo ""
    echo -e "${GREEN}Next Steps:${NC}"
    echo "1. Configure DNS to point to the load balancer IP"
    echo "2. Set up SSL certificates"
    echo "3. Configure AI API keys"
    echo "4. Import WordPress sites"
    echo "5. Set up monitoring alerts"
    echo ""

    echo -e "${CYAN}Useful Commands:${NC}"
    echo "• Check pods: kubectl get pods -n techypark"
    echo "• View logs: kubectl logs -f deployment/techypark-backend -n techypark"
    echo "• Scale app: kubectl scale deployment/techypark-backend --replicas=3 -n techypark"
    echo ""
}

# ============================================================================
# Main Execution
# ============================================================================

main() {
    # Initialize log
    echo "TechyPark Engine Ultimate Deployment - $(date)" > "$LOG_FILE"

    # Show banner
    show_banner

    # Handle arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --platform)
                CLOUD_PLATFORM="$2"
                shift 2
                ;;
            --environment)
                ENVIRONMENT="$2" 
                shift 2
                ;;
            --region)
                REGION="$2"
                shift 2
                ;;
            --dry-run)
                DRY_RUN="true"
                shift
                ;;
            --skip-confirmation)
                SKIP_CONFIRMATION="true"
                shift
                ;;
            --help)
                show_help
                exit 0
                ;;
            *)
                echo "Unknown option: $1"
                exit 1
                ;;
        esac
    done

    # Platform detection and selection
    if ! detect_platform; then
        select_platform
    fi

    # Validate platform
    if [[ ! "$CLOUD_PLATFORM" =~ ^(aws|gcp|digitalocean)$ ]]; then
        log_error "Invalid platform: $CLOUD_PLATFORM"
        exit 1
    fi

    # Check prerequisites
    check_prerequisites

    # Setup configuration
    setup_configuration

    # Show deployment summary
    echo ""
    echo -e "${CYAN}==================== DEPLOYMENT SUMMARY ====================${NC}"
    echo ""
    echo -e "${BLUE}Platform:${NC}     $CLOUD_PLATFORM"
    echo -e "${BLUE}Environment:${NC}  $ENVIRONMENT" 
    echo -e "${BLUE}Region:${NC}      $REGION"
    echo -e "${BLUE}Dry Run:${NC}     $DRY_RUN"
    echo ""

    if [[ "$SKIP_CONFIRMATION" != "true" ]]; then
        echo -e "${YELLOW}This will deploy TechyPark Engine Ultimate to $CLOUD_PLATFORM${NC}"
        echo -e "${YELLOW}The process may take 15-30 minutes to complete.${NC}"
        echo ""
        read -p "Continue with deployment? (y/N): " confirm
        if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
            log_info "Deployment cancelled by user"
            exit 0
        fi
    fi

    # Execute deployment
    log "🚀 Starting TechyPark Engine Ultimate deployment..."

    deploy_infrastructure
    build_application
    deploy_to_kubernetes
    setup_monitoring
    verify_deployment
    show_access_info

    log_success "🎉 TechyPark Engine Ultimate deployment completed successfully!"
}

show_help() {
    cat << EOF
TechyPark Engine Ultimate - Multi-Cloud Deployment

USAGE:
    ./deploy.sh [OPTIONS]

OPTIONS:
    --platform PLATFORM      Cloud platform (aws|gcp|digitalocean)
    --environment ENV         Environment (development|staging|production)
    --region REGION          Deployment region
    --dry-run                Show what would be deployed without executing
    --skip-confirmation      Skip confirmation prompts
    --help                   Show this help message

EXAMPLES:
    # Interactive deployment
    ./deploy.sh

    # Deploy to AWS production
    ./deploy.sh --platform aws --environment production --region us-east-1

    # Dry run for GCP staging
    ./deploy.sh --platform gcp --environment staging --dry-run

SUPPORTED PLATFORMS:
    • AWS (Amazon Web Services)
    • GCP (Google Cloud Platform)  
    • DigitalOcean

For more information: https://docs.techypark.com
EOF
}

# Run main function with all arguments
main "$@"
