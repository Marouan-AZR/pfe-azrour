variable "region" {
  default = "eu-west-1"
}

variable "project" {
  default = "golden-logistics"
}

variable "vpc_cidr" {
  default = "10.0.0.0/16"
}

variable "db_name" {
  default = "golden_logistics"
}

variable "db_username" {
  default = "golden"
}

variable "db_password" {
  sensitive = true
  default   = "ChangeMe123!"
}

variable "instance_type" {
  default = "t3.micro"
}

variable "db_instance_class" {
  default = "db.t3.micro"
}
