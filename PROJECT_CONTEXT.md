# PSP Training Accreditation Management System

## Purpose

Build a secure web system for managing pathology residency-training
institution accreditation, inspections, corrective actions, accreditation
decisions, renewals, and resident portfolios.

## Initial users

- PSP administrator
- CART reviewer
- Inspector
- Institution administrator
- Training officer
- Consultant
- Resident
- Read-only auditor

## Initial program types

- Anatomic Pathology
- Clinical Pathology
- Combined Anatomic and Clinical Pathology

## MVP scope

- Authentication and role-based access
- Institution and program profiles
- Accreditation applications
- Configurable compliance checklist
- Supporting document upload and review
- Inspection workflow
- Findings and corrective actions
- Accreditation decision
- Basic resident profile and portfolio

## Important principles

- Final accreditation decisions require authorized human approval.
- Checklist requirements must be configurable.
- Submitted documents must be versioned.
- Important actions must be audit logged.
- Resident and clinical information must be protected.
- Patient information must be de-identified.
- Do not hard-code PSP requirements that may change.

## Development rules

- Use migrations for database changes.
- Use seed data for local demonstration.
- Write tests for every workflow.
- Do not use production credentials.
- Do not delete historical accreditation records.
- Ask for clarification when a business rule is uncertain.