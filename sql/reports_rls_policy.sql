-- Enable RLS on reports table
ALTER TABLE reports ENABLE ROW LEVEL SECURITY;

-- Create policy for inserting reports (authenticated users only)
CREATE POLICY "Users can create reports"
ON reports
FOR INSERT
TO authenticated
WITH CHECK (
    auth.uid() = reported_by
);

-- Create policy for viewing reports (admins can see all, users can see their own)
CREATE POLICY "Users can view their own reports"
ON reports
FOR SELECT
USING (
    auth.uid() = reported_by OR
    EXISTS (
        SELECT 1 FROM profiles
        WHERE profiles.id = auth.uid()
        AND profiles.role = 'admin'
    )
);

-- Create policy for updating reports (admin only)
CREATE POLICY "Only admins can update reports"
ON reports
FOR UPDATE
USING (
    EXISTS (
        SELECT 1 FROM profiles
        WHERE profiles.id = auth.uid()
        AND profiles.role = 'admin'
    )
);
