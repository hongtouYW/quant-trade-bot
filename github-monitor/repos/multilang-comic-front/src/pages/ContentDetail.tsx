// import { useState } from "react";
// import { useParams } from "react-router";

// interface ContentDetailProps {
//   id: string;
//   title: string;
//   author: string;
//   description: string;
//   image: string;
//   rating: number;
//   status: string;
//   category: string;
//   chapters: number;
//   views: string;
//   isVip: boolean;
//   is18: boolean;
//   tags: string[];
// }

// const mockContent: ContentDetailProps = {
//   id: "1",
//   title: "命運:貞潔慾女",
//   author: "作者名",
//   description: "这是一个精彩的故事描述，讲述了主人公的冒险历程和成长故事。故事情节跌宕起伏，人物形象鲜明，是一部不可多得的优秀作品。",
//   image: "/assets/images/post1.png",
//   rating: 4.8,
//   status: "连载中",
//   category: "剧情",
//   chapters: 156,
//   views: "2.5万",
//   isVip: true,
//   is18: true,
//   tags: ["剧情", "恋爱", "校园", "青春"]
// };

// const relatedContent = [
//   {
//     id: 1,
//     image: "/assets/images/post1.png",
//     title: "相关作品1",
//     author: "作者A",
//     rating: 4.5
//   },
//   {
//     id: 2,
//     image: "/assets/images/post2.png", 
//     title: "相关作品2",
//     author: "作者B",
//     rating: 4.7
//   },
//   {
//     id: 3,
//     image: "/assets/images/post3.png",
//     title: "相关作品3", 
//     author: "作者C",
//     rating: 4.3
//   }
// ];

// const ContentDetail = () => {
//   const { id } = useParams();
//   const [isFavorited, setIsFavorited] = useState(false);
//   const [activeTab, setActiveTab] = useState("chapters");

//   const handleFavorite = () => {
//     setIsFavorited(!isFavorited);
//   };

//   const handleStartReading = () => {
//     // Navigate to reader page
//     console.log("Start reading:", id);
//   };

//   return (
//     <div className="max-w-screen-xl mx-auto px-4 py-6">
//       {/* Content Header */}
//       <div className="bg-white rounded-lg shadow-sm p-6 mb-6">
//         <div className="flex gap-6">
//           <div className="flex-shrink-0">
//             <img 
//               src={mockContent.image} 
//               alt={mockContent.title}
//               className="w-48 h-64 object-cover rounded-lg"
//             />
//           </div>
          
//           <div className="flex-1">
//             <div className="flex items-start justify-between mb-4">
//               <h1 className="text-2xl font-bold text-gray-900">{mockContent.title}</h1>
//               <button
//                 onClick={handleFavorite}
//                 className={`p-2 rounded-full ${isFavorited ? 'text-red-500' : 'text-gray-400'}`}
//               >
//                 <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
//                   <path fillRule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clipRule="evenodd" />
//                 </svg>
//               </button>
//             </div>

//             <div className="space-y-3 mb-6">
//               <div className="flex items-center gap-4">
//                 <span className="text-gray-600">作者:</span>
//                 <span className="text-blue-600 hover:underline cursor-pointer">{mockContent.author}</span>
//               </div>
              
//               <div className="flex items-center gap-4">
//                 <span className="text-gray-600">状态:</span>
//                 <span className={`px-2 py-1 rounded text-sm ${
//                   mockContent.status === '连载中' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
//                 }`}>
//                   {mockContent.status}
//                 </span>
//               </div>

//               <div className="flex items-center gap-4">
//                 <span className="text-gray-600">分类:</span>
//                 <span className="text-blue-600">{mockContent.category}</span>
//               </div>

//               <div className="flex items-center gap-4">
//                 <span className="text-gray-600">章节:</span>
//                 <span>{mockContent.chapters}话</span>
//               </div>

//               <div className="flex items-center gap-4">
//                 <span className="text-gray-600">浏览:</span>
//                 <span>{mockContent.views}</span>
//               </div>

//               <div className="flex items-center gap-4">
//                 <span className="text-gray-600">评分:</span>
//                 <div className="flex items-center gap-1">
//                   <span className="text-yellow-500">★</span>
//                   <span>{mockContent.rating}</span>
//                 </div>
//               </div>
//             </div>

//             <div className="flex flex-wrap gap-2 mb-6">
//               {mockContent.tags.map((tag, index) => (
//                 <span key={index} className="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
//                   {tag}
//                 </span>
//               ))}
//               {mockContent.isVip && (
//                 <span className="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">VIP</span>
//               )}
//               {mockContent.is18 && (
//                 <span className="px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm">18+</span>
//               )}
//             </div>

//             <div className="flex gap-3">
//               <button 
//                 onClick={handleStartReading}
//                 className="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors"
//               >
//                 开始阅读
//               </button>
//               <button className="border border-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-50 transition-colors">
//                 加入书架
//               </button>
//             </div>
//           </div>
//         </div>
//       </div>

//       {/* Description */}
//       <div className="bg-white rounded-lg shadow-sm p-6 mb-6">
//         <h2 className="text-lg font-semibold mb-3">作品简介</h2>
//         <p className="text-gray-700 leading-relaxed">{mockContent.description}</p>
//       </div>

//       {/* Tabs */}
//       <div className="bg-white rounded-lg shadow-sm">
//         <div className="border-b border-gray-200">
//           <nav className="flex space-x-8 px-6">
//             <button
//               onClick={() => setActiveTab("chapters")}
//               className={`py-4 px-1 border-b-2 font-medium text-sm ${
//                 activeTab === "chapters"
//                   ? "border-blue-500 text-blue-600"
//                   : "border-transparent text-gray-500 hover:text-gray-700"
//               }`}
//             >
//               章节列表
//             </button>
//             <button
//               onClick={() => setActiveTab("reviews")}
//               className={`py-4 px-1 border-b-2 font-medium text-sm ${
//                 activeTab === "reviews"
//                   ? "border-blue-500 text-blue-600"
//                   : "border-transparent text-gray-500 hover:text-gray-700"
//               }`}
//             >
//               评论
//             </button>
//             <button
//               onClick={() => setActiveTab("related")}
//               className={`py-4 px-1 border-b-2 font-medium text-sm ${
//                 activeTab === "related"
//                   ? "border-blue-500 text-blue-600"
//                   : "border-transparent text-gray-500 hover:text-gray-700"
//               }`}
//             >
//               相关推荐
//             </button>
//           </nav>
//         </div>

//         <div className="p-6">
//           {activeTab === "chapters" && (
//             <div className="space-y-2">
//               {Array.from({ length: 10 }, (_, i) => (
//                 <div key={i} className="flex items-center justify-between p-3 hover:bg-gray-50 rounded cursor-pointer">
//                   <span>第{i + 1}话: 章节标题</span>
//                   <span className="text-sm text-gray-500">2024-01-{String(i + 1).padStart(2, '0')}</span>
//                 </div>
//               ))}
//             </div>
//           )}

//           {activeTab === "reviews" && (
//             <div className="space-y-4">
//               <div className="text-center text-gray-500 py-8">
//                 暂无评论，快来抢沙发吧！
//               </div>
//             </div>
//           )}

//           {activeTab === "related" && (
//             <div className="grid grid-cols-3 gap-4">
//               {relatedContent.map((item) => (
//                 <div key={item.id} className="border rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer">
//                   <img src={item.image} alt={item.title} className="w-full h-32 object-cover rounded mb-3" />
//                   <h3 className="font-medium mb-1">{item.title}</h3>
//                   <p className="text-sm text-gray-600 mb-2">{item.author}</p>
//                   <div className="flex items-center">
//                     <span className="text-yellow-500">★</span>
//                     <span className="text-sm ml-1">{item.rating}</span>
//                   </div>
//                 </div>
//               ))}
//             </div>
//           )}
//         </div>
//       </div>
//     </div>
//   );
// };

// export default ContentDetail;
