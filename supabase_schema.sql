-- JejakKarier schema for Supabase PostgreSQL.
-- Tables use RLS with no public policies because the PHP backend connects
-- directly using a protected database connection string.

CREATE TABLE IF NOT EXISTS public.users (
    id BIGSERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.applications (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NULL REFERENCES public.users(id) ON DELETE CASCADE,
    company VARCHAR(150) NOT NULL,
    position VARCHAR(150) NOT NULL,
    channel VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Terkirim'
        CHECK (status IN ('Terkirim','Diproses','HR Screening','Tes','Interview','Offering','Ditolak','Diterima')),
    priority VARCHAR(20) NOT NULL DEFAULT 'Sedang'
        CHECK (priority IN ('Tinggi','Sedang','Rendah')),
    notes TEXT NULL,
    follow_up_at TIMESTAMPTZ NULL,
    interview_at TIMESTAMPTZ NULL,
    deadline_at TIMESTAMPTZ NULL,
    applied_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS public.application_status_history (
    id BIGSERIAL PRIMARY KEY,
    application_id BIGINT NOT NULL REFERENCES public.applications(id) ON DELETE CASCADE,
    status VARCHAR(50) NOT NULL,
    changed_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_applications_user_id ON public.applications(user_id);
CREATE INDEX IF NOT EXISTS idx_applications_company ON public.applications(company);
CREATE INDEX IF NOT EXISTS idx_applications_status ON public.applications(status);
CREATE INDEX IF NOT EXISTS idx_applications_priority ON public.applications(priority);
CREATE INDEX IF NOT EXISTS idx_applications_applied_at ON public.applications(applied_at);
CREATE INDEX IF NOT EXISTS idx_history_application ON public.application_status_history(application_id);
CREATE INDEX IF NOT EXISTS idx_history_changed_at ON public.application_status_history(changed_at);

CREATE OR REPLACE FUNCTION public.set_application_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS applications_updated_at ON public.applications;
CREATE TRIGGER applications_updated_at
BEFORE UPDATE ON public.applications
FOR EACH ROW EXECUTE FUNCTION public.set_application_updated_at();

ALTER TABLE public.users ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.applications ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.application_status_history ENABLE ROW LEVEL SECURITY;
