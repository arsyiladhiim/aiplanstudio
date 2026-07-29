module.exports = {
  apps: [
    {
      name: 'next-app',
      script: 'npm',
      args: 'run start',
      exec_mode: 'cluster',
      instances: '1', // Menggunakan semua core CPU yang tersedia
      environment: {
        NODE_ENV: 'production'
      }
    }
  ]
};
