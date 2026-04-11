import InfoCard from './info-card';

export const InfoCardExample = () => {
  const exampleData = [
    {
      title: 'Aijav 最新国内地址',
      links: [
        {
          label: 'www.aaa.com',
          url: 'https://www.aaa.com',
        },
      ],
    },
    {
      title: 'Aijav 海外地址(需翻墙)',
      links: [
        {
          label: 'https://ccc.com',
          url: 'https://ccc.com',
        },
        {
          label: 'https://ccc.com',
          url: 'https://ccc.com',
        },
        {
          label: 'https://ccc.com',
          url: 'https://ccc.com',
        },
      ],
    },
    {
      title: 'Aijav 永久地址(需翻墙)',
      links: [
        {
          label: 'https://aijav.com',
          url: 'https://aijav.com',
        },
      ],
    },
    {
      title: '推荐使用Edge/chrome/safari浏览器访问网站',
      content: '',
    },
    {
      title: 'Telegram (需翻墙)',
      links: [
        {
          label: '@japanav18',
          url: 'https://t.me/japanav18',
        },
      ],
    },
  ];

  return (
    <div className="flex items-center justify-center p-8">
      <InfoCard
        sections={exampleData}
        imageUrl="/path-to-profile-image.jpg"
        imageBgUrl="/path-to-bg-image.png"
        className="relative w-full max-w-md"
      />
    </div>
  );
};

export default InfoCardExample;
